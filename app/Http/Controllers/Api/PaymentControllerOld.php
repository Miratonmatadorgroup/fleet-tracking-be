<?php

namespace App\Http\Controllers\Api;


use Throwable;
use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Http\Request;
use App\Models\TransportMode;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Enums\PaymentStatusEnums;
use Illuminate\Http\JsonResponse;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Mail\DeliveryAssignedToUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\DTOs\Payment\PayWithWalletDTO;
use App\Mail\DeliveryAssignedToDriver;
use App\Mail\PaymentSuccessButNoDriverYet;
use App\Actions\Payment\PayWithWalletAction;
use App\Events\Payment\PaymentSummaryViewed;
use App\Services\Payments\ShanonoPayService;
use App\Actions\Payment\GetPaymentSummaryAction;
use App\Services\Payments\PaymentServiceInterface;


class PaymentControllerOld extends Controller
{
    protected PaymentServiceInterface $paymentGateway;
    protected $twilio;
    protected $termii;


    public function __construct(TwilioService $twilio, TermiiService $termii)
    {

        $gatewayClass = config('payments.gateway_class', ShanonoPayService::class);
        $this->paymentGateway = App::make($gatewayClass);
        $this->twilio = $twilio;
        $this->termii = $termii;
    }

    public function initiate(Request $request)
    {
        $delivery = Delivery::findOrFail($request->delivery_id);
        $user = $delivery->customer;

        $payload = [
            'amount' => $delivery->total_price,
            'email' => $user->email ?? null,
            'phone' => $user->phone ?? null,
            'whatsapp' => $user->whatsapp_number ?? null,
            'callback_url' => route('payment.verify'),
            'metadata' => [
                'delivery_id' => $delivery->id,
                'user_id' => $user->id,
            ],
        ];

        $data = $this->paymentGateway->initialize($payload);

        return response()->json($data);
    }


    public function verify(Request $request)
    {
        Log::info('Verify method hit', [
            'reference' => $request->query('reference'),
            'delivery_id' => $request->query('delivery_id'),
        ]);
        $reference = $request->query('reference');
        $deliveryId = $request->query('delivery_id');

        $verification = $this->paymentGateway->verify($reference);
        Log::info('Payment verification result', $verification);

        $deliveryId = $verification['data']['metadata']['delivery_id'] ?? $deliveryId;

        $delivery = $deliveryId ? Delivery::with('customer')->find($deliveryId) : null;
        $payment = $deliveryId ? Payment::where('delivery_id', $deliveryId)->first() : null;


        if ($verification['status'] && $verification['data']['status'] === 'success') {
            if ($delivery && !$delivery->tracking_number) {
                $delivery->tracking_number = $this->generateTrackingNumber();
                $delivery->status = DeliveryStatusEnums::BOOKED->value;
                $delivery->save();

                $transport = TransportMode::with('driver')
                    ->where('type', $delivery->mode_of_transportation)
                    ->whereNotNull('driver_id')
                    ->whereHas('driver', function ($query) use ($delivery) {
                        $query->whereIn('status', [DriverStatusEnums::ACTIVE, DriverStatusEnums::AVAILABLE])
                            ->whereDoesntHave('deliveries', function ($q) use ($delivery) {
                                $q->where('delivery_date', $delivery->delivery_date)
                                    ->where('delivery_time', $delivery->delivery_time);
                            });
                    })
                    ->first();

                if ($transport && $transport->driver) {
                    $driver = $transport->driver;

                    $delivery->driver_id = $driver->id;
                    $delivery->transport_mode_id = $transport->id;
                    $delivery->save();

                    $driver->status = DriverStatusEnums::UNAVAILABLE;
                    $driver->save();

                    //Driver Email
                    try {
                        Mail::to($driver->email)->send(new DeliveryAssignedToDriver($delivery, $driver));
                    } catch (\Throwable $e) {
                        Log::error('Driver email failed', ['error' => $e->getMessage()]);
                    }

                    //SMS + WhatsApp to driver
                    $msg = "Hi {$driver->name}, you have a new LoopFreight delivery for {$delivery->delivery_date} at {$delivery->delivery_time}. From: {$delivery->pickup_location} to {$delivery->dropoff_location}.";
                    try {
                        $this->termii->sendSms($driver->phone, $msg);
                    } catch (\Throwable $e) {
                        Log::error('Driver SMS failed', ['error' => $e->getMessage()]);
                    }

                    try {
                        $this->twilio->sendWhatsAppMessage($driver->phone, $msg);
                    } catch (\Throwable $e) {
                        Log::error('Driver WhatsApp failed', ['error' => $e->getMessage()]);
                    }

                    //Notify Customer with driver + transport info
                    try {
                        Mail::to($delivery->customer->email)->send(new DeliveryAssignedToUser($delivery, $driver, $transport, $payment));
                    } catch (\Throwable $e) {
                        Log::error('Customer email with driver failed', ['error' => $e->getMessage()]);
                    }
                } else {
                    //No available driver — notify sender only
                    $customer = $delivery->customer;
                    $tracking = $delivery->tracking_number;

                    $fallbackMsg = "Hi {$customer->name}, your LoopFreight payment was successful. Tracking No: {$tracking}. We will assign a driver shortly and notify you.";

                    try {
                        if ($customer->email) {
                            Mail::to($customer->email)->send(new PaymentSuccessButNoDriverYet($delivery));
                        }
                    } catch (\Throwable $e) {
                        Log::error('Fallback email failed', ['error' => $e->getMessage()]);
                    }

                    try {
                        if ($customer->phone) {
                            $this->termii->sendSms($customer->phone, $fallbackMsg);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Fallback SMS failed', ['error' => $e->getMessage()]);
                    }

                    try {
                        if ($customer->whatsapp_number) {
                            $this->twilio->sendWhatsAppMessage($customer->whatsapp_number, $fallbackMsg);
                        }
                    } catch (\Throwable $e) {
                        Log::error('Fallback WhatsApp failed', ['error' => $e->getMessage()]);
                    }

                    try {
                        $admins = \App\Models\User::role('admin')->get();
                        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\Admin\NoAvailableDriverNotification($delivery));
                    } catch (\Throwable $e) {
                        Log::error('Admin no-driver notification failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            if ($payment) {
                $payment->status = PaymentStatusEnums::PAID->value;
                if ($payment->reference !== $reference) {
                    $payment->reference = $reference;
                }
                $payment->save();
            }

            return $this->success($delivery);
        }

        if ($payment) {
            $payment->status = PaymentStatusEnums::FAILED->value;

            if ($payment->reference !== $reference) {
                $payment->reference = $reference;
            }

            $payment->save();
        }

        return $this->failed();
    }

    public function success($delivery = null)
    {
        if (!$delivery) {
            $deliveryId = request()->query('delivery_id');
            $delivery = Delivery::with('customer')->findOrFail($deliveryId);
        }

        return successResponse('Payment successful.', [
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
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

    public function getPaymentSummary(GetPaymentSummaryAction $action): JsonResponse
    {
        try {
            $summary = $action->execute();

            event(new PaymentSummaryViewed(Auth::id(), [
                'total_amount'       => $summary->totalAmount,
                'total_delivery'     => $summary->deliveryTotal,
                'total_investment'   => $summary->investmentTotal,
                'delivery_breakdown' => $summary->deliveryBreakdown,
            ]));

            return successResponse('Platform summary fetched successfully.', [
                'total_amount'         => $summary->totalAmount,
                'total_delivery'       => $summary->deliveryTotal,
                'total_investment'     => $summary->investmentTotal,
                'delivery_breakdown'   => $summary->deliveryBreakdown,
                'investment_breakdown' => $summary->investmentBreakdown,
            ]);
        } catch (Throwable $th) {
            return failureResponse('Error fetching platform summary.', 500, 'payment_summary_error', $th);
        }
    }



    protected function generateTrackingNumber(): string
    {
        return 'LPF-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }
}
