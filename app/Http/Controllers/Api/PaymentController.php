<?php

namespace App\Http\Controllers\Api;


use Throwable;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\DriverService;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\PaymentStatusEnums;
use Illuminate\Http\JsonResponse;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\DTOs\Payment\PayWithWalletDTO;
use App\Enums\DeliveryAssignmentLogsEnums;
use App\Services\DeliveryAssignmentService;
use App\Actions\Payment\PayWithWalletAction;
use App\Events\Payment\PaymentSummaryViewed;
use App\Services\Payments\ShanonoPayService;
use App\Services\Payments\MockPaymentService;
use App\Events\Delivery\DeliveryAssignedEvent;
use App\Actions\Payment\GetPaymentSummaryAction;
use App\Actions\Payment\GetEarningsSummaryAction;


class PaymentController extends Controller
{
    protected $twilio;
    protected $termii;

    private DeliveryAssignmentService $assignmentService;

    public function __construct(
        TwilioService $twilio,
        TermiiService $termii,
        DeliveryAssignmentService $assignmentService
    ) {
        $this->twilio = $twilio;
        $this->termii = $termii;
        $this->assignmentService = $assignmentService;
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'delivery_id' => 'required|uuid|exists:deliveries,id',
        ]);

        $delivery = Delivery::findOrFail($request->delivery_id);

        $gateway = config('payments.gateway', 'mock');

        $serviceClass = match ($gateway) {
            'shanono' => ShanonoPayService::class,
            default   => MockPaymentService::class,
        };

        $service = app($serviceClass);

        $paymentData = $service->initiate($delivery);

        $providerErrors = data_get($paymentData, 'raw.errors', []);

        if (!empty($providerErrors)) {
            if (isset($providerErrors['mobile'])) {
                $friendlyMessage = 'Mobile field required, please update your profile.';
            } elseif (isset($providerErrors['email'])) {
                $friendlyMessage = 'Email field required, please update your profile.';
            } elseif (isset($providerErrors['name'])) {
                $friendlyMessage = 'Name field required, please update your profile.';
            } else {
                $friendlyMessage = data_get($paymentData, 'raw.message', 'Payment request failed.');
            }


            Log::warning('Payment initiation rejected by provider', [
                'gateway'     => $gateway,
                'delivery_id' => $delivery->id,
                'errors'      => $providerErrors,
            ]);

            return response()->json([
                'success' => false,
                'message' => $friendlyMessage,
                'raw'     => $paymentData,
            ], 422);
        }

        $reference = data_get($paymentData, 'reference')
            ?? data_get($paymentData, 'data.reference')
            ?? data_get($paymentData, 'data.data.reference')
            ?? data_get($paymentData, 'data.data.referecne'); //provider typo

        if (!$reference) {
            Log::error('Payment initiation failed: missing reference', [
                'gateway'     => $gateway,
                'delivery_id' => $delivery->id,
                'raw'         => $paymentData,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed: missing reference.',
                'raw'     => $paymentData,
            ], 422);
        }

        $amount = (float) (
            data_get($paymentData, 'data.data.amount')
            ?? data_get($paymentData, 'data.amount')
            ?? $delivery->total_price
            ?? 0
        );

        $amount = max(0, round($amount, 2));

        $callbackUrl = route('payments.callback', [
            'delivery_id' => $delivery->id,
            'reference'   => $reference,
        ]);

        $webhookUrl = route('payments.webhook', [
            'delivery_id' => $delivery->id,
            'reference'   => $reference,
        ]);

        try {
            DB::beginTransaction();

            $payment = Payment::updateOrCreate(
                ['delivery_id' => $delivery->id],
                [
                    'user_id'      => $delivery->customer_id,
                    'status'       => PaymentStatusEnums::PENDING->value,
                    'reference'    => $reference,
                    'amount'       => $amount,
                    'currency'     => 'NGN',
                    'gateway'      => $serviceClass,
                    'callback_url' => $callbackUrl,
                    'meta'         => $paymentData['data'] ?? null,
                ]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment initiation DB error', [
                'gateway'     => $gateway,
                'delivery_id' => $delivery->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed. Please try again.',
            ], 500);
        }

        $verifyUrl = route('payment.verify', [
            'reference'   => $reference,
            'delivery_id' => $delivery->id,
        ]);

        Log::info('Payment initiated successfully', [
            'gateway'     => $gateway,
            'delivery_id' => $delivery->id,
            'reference'   => $reference,
            'amount'      => $amount,
            'payment_id'  => $payment->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized (widget mode)',
            'data'    => array_merge($paymentData, [
                'verify_url'  => $verifyUrl,
                'payment_id'  => $payment->id,
                'callback_url' => $callbackUrl,
                'webhook_url' => $webhookUrl,
            ]),
        ]);
    }


    /**
     * GET callback from Shanono after checkout redirect
     */
    public function redirectHandler(Request $request)
    {
        $reference  = $request->query('reference');
        $deliveryId = $request->query('delivery_id');

        Log::info('Shanono redirect callback', [
            'reference'   => $reference,
            'delivery_id' => $deliveryId,
            'all' => $request->all()
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'Redirect callback received. Waiting for confirmation.',
            'reference'   => $reference,
            'delivery_id' => $deliveryId,
        ]);
    }

    /**
     * POST webhook from Shanono
     */

    public function webhookHandler(Request $request)
    {
        Log::info('Shanono webhook payload', $request->all());

        $gatewayReference = $request->input('reference');
        $deliveryId       = $request->input('delivery_id');

        $payment = Payment::where('delivery_id', $deliveryId)->first();

        if (!$payment) {
            Log::warning('Webhook payment not found', ['delivery_id' => $deliveryId]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $paymentStatus = $payment->status instanceof \App\Enums\PaymentStatusEnums
            ? $payment->status->value
            : (string) $payment->status;

        if (strtolower($paymentStatus) === 'paid') {
            Log::info('Webhook payment already marked as paid, skipping', ['payment_id' => $payment->id]);
            return response()->json(['message' => 'Already processed'], 200);
        }

        $payment->reference = $gatewayReference;

        $meta = is_array($payment->meta)
            ? $payment->meta
            : (is_string($payment->meta) ? json_decode($payment->meta, true) : []);

        $meta['gateway_reference'] = $gatewayReference;
        $payment->meta = json_encode($meta);

        $payment->save();

        $req = new Request([
            'reference'   => $gatewayReference,
            'delivery_id' => $deliveryId,
        ]);

        return $this->verify($req);
    }


    public function verify(Request $request)
    {
        $reference  = $request->input('reference') ?? $request->query('reference');
        $deliveryId = $request->input('delivery_id') ?? $request->query('delivery_id');

        Log::info('verify() called from redirect', compact('reference', 'deliveryId'));

        $gateway = config('payments.gateway', 'mock');
        $serviceClass = match ($gateway) {
            'shanono' => ShanonoPayService::class,
            default   => MockPaymentService::class,
        };

        $service = app($serviceClass);
        $verification = $service->verify($reference, $deliveryId);

        Log::info('verify() service response', ['verification' => $verification]);

        // if (!($verification['status'] ?? false)) {
        //     return $this->handleFailedPayment($reference, $deliveryId);
        // }

        if (!($verification['status'] ?? false)) {
            Log::error('Payment verification failed - returning debug info', [
                'verification' => $verification
            ]);

            return response()->json([
                'success' => false,
                'message' => $verification['message'] ?? 'Payment verification failed',
                'delivery_id' => $deliveryId,
                'status' => 'failed',
                'debug' => $verification['raw'] ?? [],
            ], 400);
        }

        $payment  = Payment::where('reference', $reference)->first();
        if (!$payment) {
            $payment = Payment::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gateway_reference')) = ?", [$reference])->first();
        }

        $delivery = $payment ? Delivery::with('customer')->find($payment->delivery_id) : null;

        if (!$delivery) {
            Log::error('verify(): delivery not found', ['reference' => $reference]);
            return $this->failed();
        }

        if (in_array($delivery->status, [
            DeliveryStatusEnums::BOOKED->value,
            DeliveryStatusEnums::QUEUED->value,
            DeliveryStatusEnums::IN_TRANSIT->value,
        ])) {
            Log::info("Delivery already processed, skipping verification.", [
                'delivery_id' => $delivery->id
            ]);

            return $this->success($delivery);
        }


        $status = strtolower($verification['data']['status'] ?? '');
        $successStatuses = ['success', 'successful', 'paid', 'true', 'approved', 'completed', 'settled'];
        $isSuccess = in_array($status, $successStatuses);

        if ($isSuccess) {
            $this->handleSuccessfulPayment($delivery, $payment, $reference);
            return $this->success($delivery);
        }

        return $this->handleFailedPayment($reference, $delivery->id, $payment);
    }


    private function handleSuccessfulPayment(Delivery $delivery, ?Payment $payment, string $reference): void
    {
        if (!$delivery->tracking_number) {
            $delivery->tracking_number = $this->generateTrackingNumber();
            $delivery->waybill_number = $this->generateWaybillNumber();
            $delivery->status = DeliveryStatusEnums::BOOKED->value;
            $delivery->save();

            $driver = app(DriverService::class)->findNearestAvailable($delivery);

            if ($driver) {
                Log::info("Driver {$driver->id} assigned to Delivery {$delivery->id}");

                $delivery->driver_assigned_at = now();
                $delivery->save();

                event(new DeliveryAssignedEvent($delivery, $driver, DeliveryAssignmentLogsEnums::SUCCESS));

                // Notify
                $this->assignmentService->notifyParties($delivery, $driver, $payment);
            } else {
                Log::warning("No driver found for Delivery {$delivery->id}, queued for later retry.");

                $delivery->status = DeliveryStatusEnums::QUEUED->value;
                $delivery->driver_assigned_at = now();
                $delivery->save();

                event(new DeliveryAssignedEvent($delivery, null, DeliveryAssignmentLogsEnums::QUEUED));

                // Notify
                $this->assignmentService->notifyParties($delivery, null, $payment);
            }
        }

        if ($payment) {
            $payment->status = PaymentStatusEnums::PAID->value;
            $payment->reference = $reference;
            $payment->save();
        }

        if (!$delivery->customer_id) {
            $this->notifyGuestSender($delivery, $driver ?? null);
        } else {
            // Registered user — send system notifications
            $this->assignmentService->notifyParties($delivery, $driver ?? null, $payment);
        }
    }

    private function notifyGuestSender(Delivery $delivery, ?Driver $driver = null): void
    {
        $notificationService = app(\App\Services\NotificationService::class);

        $driverInfo = $driver
            ? "Driver Contact: {$driver->name}, Phone: {$driver->phone}, WhatsApp: {$driver->whatsapp_number}."
            : "A driver will be assigned to your delivery shortly.";

        $amount = $delivery->total_price ?? 'N/A';
        $transportMode = $delivery->transport_mode ?? 'Not specified';

        $msg = "Hi {$delivery->sender_name}, your delivery has been successfully booked and payment confirmed.
        Tracking No: {$delivery->tracking_number}
        Waybill No: {$delivery->waybill_number}
        Receiver: {$delivery->receiver_name}
        Receiver Contact: {$delivery->receiver_phone}
        Transport Mode: {$transportMode}
        Total Amount: ₦{$amount}
        {$driverInfo}";

        $notificationService->notifyGuest(
            $delivery->sender_phone,
            $delivery->sender_whatsapp_number,
            $delivery->sender_email,
            $msg
        );
    }


    /**
     * Handle failed payments
     */
    private function handleFailedPayment(string $reference, ?string $deliveryId, ?Payment $payment = null)
    {
        if ($payment) {
            $payment->status = PaymentStatusEnums::FAILED->value;
            $payment->reference = $reference;
            $payment->save();
        }

        return $this->failed();
    }

    /**
     * Notify driver, customer, and fallback admins if no driver
     */

    public function success($delivery = null)
    {
        if (!$delivery) {
            $deliveryId = request()->query('delivery_id');
            $delivery = Delivery::with('customer')->findOrFail($deliveryId);
        }

        return successResponse('Payment successful.', [
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'waybill_number'   => $delivery->waybill_number,
            'delivery' => $delivery,
            'status' => 'success',
        ]);
    }

    public function failed()
    {
        $deliveryId = request()->query('delivery_id');

        return failureResponse([
            'message' => 'Payment failed.',
            'delivery_id' => $deliveryId,
            'status' => 'failed',
        ]);
    }


    public function payWithWallet(Request $request, PayWithWalletAction $payWithWalletAction)
    {
        try {
            $dto = PayWithWalletDTO::fromRequest($request);
            $delivery = $payWithWalletAction->execute($dto);
            return successResponse('Payment successful via wallet.', $delivery);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 422);
        }
    }

    public function adminPayWithWallet(Request $request, PayWithWalletAction $payWithWalletAction)
    {
        try {
            $request->validate([
                'delivery_id' => 'required|uuid',
                'transaction_pin' => 'required|size:4',
            ]);

            $delivery = $payWithWalletAction->adminExecute(
                $request->delivery_id,
                null,
                $request->transaction_pin
            );

            return successResponse('Admin payment successful via wallet.', $delivery);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 422);
        }
    }



    public function getPaymentSummary(GetPaymentSummaryAction $action): JsonResponse
    {
        try {
            $summary = $action->execute();

            event(new PaymentSummaryViewed(Auth::id(), [
                'total_amount'       => $summary->totalCollected,
                'total_delivery'     => $summary->deliveryTotal,
                'delivery_revenue'     => $summary->deliveryRevenue,
                'total_investment'   => $summary->investmentTotal,
                'delivery_breakdown' => $summary->deliveryBreakdown,
            ]));

            return successResponse('Platform summary fetched successfully.', [
                'total_original'       => $summary->totalOriginal,
                'total_collected'         => $summary->totalCollected,
                'total_delivery'       => $summary->deliveryTotal,
                'delivery_revenue'     => $summary->deliveryRevenue,
                'total_subsidy'       => $summary->totalSubsidy,
                'total_investment'     => $summary->investmentTotal,
                'delivery_revenue_breakdown'   => $summary->deliveryBreakdown,
                'investment_breakdown' => $summary->investmentBreakdown,
            ]);
        } catch (Throwable $th) {
            return failureResponse('Error fetching platform summary.', 500, 'payment_summary_error', $th);
        }
    }

    public function getEarningsSummary(GetEarningsSummaryAction $action): JsonResponse
    {
        try {
            $range = request()->query('range'); // e.g. "daily", "weekly", etc.
            $summary = $action->execute($range);

            return successResponse('Earnings summary fetched successfully.', $summary);
        } catch (\Throwable $th) {
            return failureResponse(
                'Error fetching earnings summary.',
                500,
                'earnings_summary_error',
                $th
            );
        }
    }


    protected function generateTrackingNumber(): string
    {
        return 'LPF-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }


    protected function generateWaybillNumber(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';

        $code = $letters[random_int(0, strlen($letters) - 1)] .
            $numbers[random_int(0, strlen($numbers) - 1)];

        $pool = $letters . $numbers;
        for ($i = 0; $i < 3; $i++) {
            $code .= $pool[random_int(0, strlen($pool) - 1)];
        }

        $code = str_shuffle($code);

        return 'WB-' . $code;
    }
}
