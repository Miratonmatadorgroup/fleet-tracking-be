<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\Investor;
use Illuminate\Http\Request;
use App\Models\InvestmentPlan;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\PaymentMethodEnums;
use App\Enums\PaymentStatusEnums;
use App\Enums\InvestorStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReinvestmentSuccessMail;
use Illuminate\Validation\Rules\Enum;
use App\Services\Payments\ShanonoPayService;
use App\Mail\InvestmentPaymentSuccessfulMail;
use App\Services\Payments\PaymentServiceInterface;
use App\Actions\Investor\PayInvestmentFromWalletAction;
use App\Actions\Investor\PayReinvestmentFromWalletAction;
use App\Notifications\ReInvestmentPaymentSuccessfulNotification;
use App\Notifications\User\InvestmentPaymentSuccessfulNotification;

class InvestmentPaymentController extends Controller
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
        $request->validate([
            'investor_id' => 'required|uuid|exists:investors,id',
        ]);

        try {
            $investor = Investor::with('user')->findOrFail($request->investor_id);
            $user     = $investor->user;

            $investment = (object) [
                'id'          => $investor->id,
                'sender_name' => $user->name,
                'total_price' => (float) $investor->investment_amount,
                'customer'    => (object) [
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ];

            $gatewayData = $this->paymentGateway->initiate($investment);

            $gatewayRef = data_get($gatewayData, 'reference');
            if (!$gatewayRef) {
                throw new \Exception('No gateway reference returned from Shanono');
            }

            // Persist payment
            $payment = Payment::updateOrCreate(
                [
                    'user_id'   => $user->id,
                    'reference' => $gatewayRef,
                ],
                [
                    'status'   => PaymentStatusEnums::PENDING->value,
                    'amount'   => $investor->investment_amount,
                    'currency' => 'NGN',
                    'meta'     => [
                        'investor_id'       => $investor->id,
                        'gateway_reference' => $gatewayRef,
                        'raw_initiate'      => $gatewayData,
                    ],
                ]
            );


            $verifyUrl = route('investment.verify', [
                'reference'   => $gatewayRef,
                'investor_id' => $investor->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Investment payment initialized',
                'data'    => array_merge($gatewayData, [
                    'verify_url'  => $verifyUrl,
                    'payment_id'  => $payment->id,
                    'investor_id' => $investor->id,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Investment initiate failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return failureResponse('Error initiating investment payment');
        }
    }

    public function redirectHandler(Request $request)
    {
        $reference  = $request->query('reference');
        $investorId = $request->query('investor_id');

        Log::info('Investment redirect callback', compact('reference', 'investorId'));

        return response()->json([
            'success'     => true,
            'message'     => 'Redirect callback received. Waiting for confirmation.',
            'reference'   => $reference,
            'investor_id' => $investorId,
        ]);
    }

    public function webhookHandler(Request $request)
    {
        Log::info('Investment webhook payload', $request->all());

        $gatewayReference = $request->input('reference');
        $investorId       = $request->input('investor_id');

        $req = new Request([
            'reference'    => $gatewayReference,
            'investor_id'  => $investorId,
        ]);

        return $this->verify($req);
    }

    public function verify(Request $request)
    {
        $reference  = $request->input('reference');
        $investorId = $request->input('investor_id');

        Log::info('Investment verify called', compact('reference', 'investorId'));
        $driver = DB::getDriverName();
        $referenceColumn = $driver === 'pgsql'
            ? "meta->>'gateway_reference'"
            : "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gateway_reference'))";

        $payment = Payment::where('reference', $reference)
            ->orWhereRaw("$referenceColumn = ?", [$reference])
            ->first();

        if (!$payment) {
            return failureResponse('Payment not found');
        }

        if ($payment->status === PaymentStatusEnums::PAID->value) {
            return successResponse('Payment already verified', [
                'investor_id' => data_get($payment->meta, 'investor_id'),
                'reference'   => $payment->reference,
                'status'      => 'success',
            ]);
        }

        $verification = $this->paymentGateway->verify($reference, $investorId);

        if ($verification['status']) {
            $investor = Investor::with('user')->find($investorId);

            if ($investor) {
                $investor->status = InvestorStatusEnums::ACTIVE;
                $investor->save();

                $user = $investor->user;

                // Update payment
                $payment->update([
                    'status' => PaymentStatusEnums::PAID->value,
                    'meta'   => array_merge($payment->meta ?? [], [
                        'gateway_reference' => $reference,
                        'verified_data'     => $verification,
                    ]),
                ]);

                Mail::to($user->email)->send(new InvestmentPaymentSuccessfulMail($investor));


                $user->notify(new InvestmentPaymentSuccessfulNotification(
                    (string) $payment->amount,
                    $payment->reference
                ));


                try {
                    $message = "Hi {$user->name}, your LoopFreight investment payment of ₦{$payment->amount} was successful. Ref: {$payment->reference}";
                    $this->termii->sendSms($user->phone, $message);
                    $this->twilio->sendWhatsAppMessage($user->whatsapp_number, $message);
                } catch (\Throwable $e) {
                    Log::error('Failed to send SMS for investment payment', [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id,
                        'reference' => $payment->reference,
                    ]);
                }
            }

            return successResponse('Investment payment verified successfully.', [
                'investor_id' => $investorId,
                'reference'   => $payment->reference,
                'status'      => 'success',
            ]);
        }

        $payment->update(['status' => PaymentStatusEnums::FAILED->value]);
        return failureResponse('Investment payment failed');
    }

    public function success()
    {
        $investorId = request()->query('investor_id');
        $investor = Investor::with('user')->findOrFail($investorId);

        return successResponse('Investment payment successful.', [
            'investor_id' => $investor->id,
            'status'      => 'success',
        ]);
    }

    public function failed()
    {
        $investorId = request()->query('investor_id');

        return failureResponse([
            'message'     => 'Investment payment failed.',
            'investor_id' => $investorId,
            'status'      => 'failed',
        ]);
    }


    // public function investorPayFromWallet(Investor $investor, PayInvestmentFromWalletAction $action)
    // {
    //     try {
    //         $payment = $action->execute($investor);

    //         return successResponse("Investment paid successfully from wallet.", $payment);
    //     } catch (\Throwable $th) {
    //         return failureResponse(
    //             $th->getMessage(),
    //             400,
    //             'INVESTOR_WALLET_PAYMENT_ERROR',
    //             $th
    //         );
    //     }
    // }


    public function investorPayFromWallet(Investor $investor, PayInvestmentFromWalletAction $action)
    {
        try {
            $validated = request()->validate([
                'transaction_pin' => 'required|string|min:4|max:6'
            ]);

            $payment = $action->execute($investor, $validated['transaction_pin']);

            return successResponse("Investment paid successfully from wallet.", $payment);
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                400,
                'INVESTOR_WALLET_PAYMENT_ERROR',
                $th
            );
        }
    }



    // FOR REINVESMENT STARTS HERE
    public function reinvestInitiate(Request $request)
    {
        $request->validate([
            'investor_id'        => 'required|uuid|exists:investors,id',
            'investment_plan_id' => 'required|exists:investment_plans,id',
            'payment_method'     => ['nullable', new Enum(PaymentMethodEnums::class)],
        ]);

        try {
            $investor = Investor::with('user')->findOrFail($request->investor_id);
            $user     = $investor->user;

            $planId = $request->get('investment_plan_id');
            $plan   = InvestmentPlan::findOrFail($planId);
            $amount = (float) $plan->amount;

            $investment = (object) [
                'id'          => $investor->id,
                'sender_name' => $user->name,
                'total_price' => $amount,
                'customer'    => (object) [
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ];

            $gatewayData = $this->paymentGateway->initiate($investment);
            $gatewayRef  = data_get($gatewayData, 'reference');

            if (!$gatewayRef) {
                throw new \Exception('No gateway reference returned from payment gateway.');
            }

            $meta = [
                'investor_id'        => $investor->id,
                'investment_plan_id' => $planId,
                'gateway_reference'  => $gatewayRef,
                'reinvestment'       => true,
            ];

            if ($request->filled('payment_method')) {
                $meta['payment_method'] = $request->payment_method;
            }

            // Store payment
            $payment = Payment::create([
                'user_id'   => $user->id,
                'reference' => $gatewayRef,
                'status'    => PaymentStatusEnums::PENDING,
                'amount'    => $amount,
                'currency'  => 'NGN',
                'meta'      => $meta,
            ]);

            $verifyUrl = route('reinvestment.verify', [
                'reference'   => $gatewayRef,
                'investor_id' => $investor->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reinvestment payment initialized.',
                'data'    => array_merge($gatewayData, [
                    'verify_url'  => $verifyUrl,
                    'payment_id'  => $payment->id,
                    'investor_id' => $investor->id,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Reinvestment initiate failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return failureResponse('Error initiating reinvestment payment.');
        }
    }


    public function reinvestVerify(Request $request)
    {
        $reference  = $request->input('reference');
        $investorId = $request->input('investor_id');

        Log::info('Reinvestment verify called', compact('reference', 'investorId'));

        $referenceColumn = DB::getDriverName() === 'pgsql'
            ? "meta->>'gateway_reference'"
            : "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gateway_reference'))";

        $payment = Payment::where('reference', $reference)
            ->orWhereRaw("$referenceColumn = ?", [$reference])
            ->first();

        if (!$payment) {
            return failureResponse('Reinvestment payment not found.');
        }

        if ($payment->status === PaymentStatusEnums::PAID->value) {
            return successResponse('Reinvestment already verified.', [
                'investor_id' => data_get($payment->meta, 'investor_id'),
                'reference'   => $payment->reference,
                'status'      => 'success',
            ]);
        }

        $verification = $this->paymentGateway->verify($reference, $investorId);

        if ($verification['status']) {
            $investor = Investor::with('user')->find($investorId);
            if (!$investor) return failureResponse('Investor not found.');

            $user     = $investor->user;
            $meta     = $payment->meta ?? [];
            $amount   = (float) $payment->amount;
            $reinvest = data_get($meta, 'reinvestment', false);

            if ($reinvest) {
                $investor->investment_amount += $amount;

                if (isset($meta['payment_method'])) {
                    $investor->payment_method = $meta['payment_method'];
                }

                $investor->save();
            }

            $payment->update([
                'status' => PaymentStatusEnums::PAID->value,
                'meta'   => array_merge($payment->meta ?? [], [
                    'gateway_reference' => $reference,
                    'verified_data'     => $verification,
                ]),
            ]);

            // Send Email
            Mail::to($user->email)->send(new ReinvestmentSuccessMail($user, $amount));

            // In-App Notification
            $user->notify(new ReInvestmentPaymentSuccessfulNotification(
                (string) $payment->amount,
                $payment->reference
            ));

            // SMS + WhatsApp
            try {
                $message = "Hi {$user->name}, your LoopFreight reinvestment of ₦{$amount} was successful. Ref: {$payment->reference}";
                $this->termii->sendSms($user->phone, $message);
                $this->twilio->sendWhatsAppMessage($user->whatsapp_number, $message);
            } catch (\Throwable $e) {
                Log::error('Failed to send SMS/WhatsApp for reinvestment', [
                    'error'      => $e->getMessage(),
                    'user_id'    => $user->id,
                    'reference'  => $payment->reference,
                ]);
            }

            return successResponse('Reinvestment verified and completed.', [
                'investor_id' => $investorId,
                'reference'   => $payment->reference,
                'status'      => 'success',
            ]);
        }

        $payment->update(['status' => PaymentStatusEnums::FAILED->value]);
        return failureResponse('Reinvestment payment verification failed.');
    }

    // REINVESTMENT PAYMENT FROM WALLET STARTS HERE
    public function reinvestPayFromWallet(Request $request, PayReinvestmentFromWalletAction $action)
    {
        $request->validate([
            'investor_id'        => 'required|uuid|exists:investors,id',
            'investment_plan_id' => 'required|exists:investment_plans,id',
            'transaction_pin' => 'required|string|min:4|max:6'

        ]);

        try {
            $investor = Investor::with('user')->findOrFail($request->investor_id);
            $plan     = InvestmentPlan::findOrFail($request->investment_plan_id);

            $payment = $action->execute($investor, $plan, $request->transaction_pin);

            return successResponse("Reinvestment paid successfully from wallet.", $payment);
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                400,
                'REINVEST_WALLET_PAYMENT_ERROR',
                $th
            );
        }
    }
    // REINVESTMENT PAYMENT FROM WALLET ENDS HERE


    // FOR REINVESTMENT ENDS HERE
}
