<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Mail\WalletCreditedMail;
use App\Enums\PaymentStatusEnums;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Services\Payments\ShanonoPayService;
use App\Notifications\WalletCreditedNotification;

class WalletPaymentController extends Controller
{
    protected ShanonoPayService $paymentGateway;
    protected $twilio;
    protected $termii;

    public function __construct(TwilioService $twilio, TermiiService $termii )
    {
        $this->paymentGateway = new ShanonoPayService();
        $this->twilio = $twilio;
        $this->termii = $termii;
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'amount'  => 'required|numeric|min:100',
        ]);

        $user = User::findOrFail($request->user_id);
        $wallet = $user->wallet;

        $walletObject = (object) [
            'id' => $wallet->id,
            'sender_name' => $user->name,
            'total_price' => $request->amount,
            'customer' => (object) [
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        ];

        $gatewayData = $this->paymentGateway->initiate($walletObject);
        $gatewayRef = data_get($gatewayData, 'reference');

        if (!$gatewayRef) {
            return failureResponse('No gateway reference returned from Shanono');
        }

        Payment::updateOrCreate([
            'user_id' => $user->id,
            'reference' => $gatewayRef,
        ], [
            'status' => PaymentStatusEnums::PENDING,
            'amount' => $request->amount,
            'currency' => 'NGN',
            'meta' => [
                'wallet_id' => $wallet->id,
                'gateway_reference' => $gatewayRef,
                'raw_initiate' => $gatewayData,
            ],
        ]);

        return successResponse('Wallet payment initiated.', [
            ...$gatewayData,
            'verify_url' => route('wallet.verify', [
                'reference' => $gatewayRef,
                'wallet_id' => $wallet->id,
            ]),
            'payment_id' => $gatewayRef,
        ]);
    }

    public function webhook(Request $request)
    {
        Log::info('Wallet webhook called', $request->all());

        $reference = $request->input('reference');
        $walletId  = $request->input('wallet_id');

        return $this->verify(new Request([
            'reference'  => $reference,
            'wallet_id'  => $walletId,
        ]));
    }

    public function verify(Request $request)
    {
        $reference = $request->query('reference');
        $walletId  = $request->query('wallet_id');

        Log::info('Wallet verify called', compact('reference', 'walletId'));

        $driver = DB::getDriverName();
        $referenceColumn = $driver === 'pgsql'
            ? "meta->>'gateway_reference'"
            : "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gateway_reference'))";

        $payment = Payment::where('reference', $reference)->first();


        // $payment = Payment::where('reference', $reference)
        //     ->orWhereRaw("$referenceColumn = ?", [$reference])
        //     ->first();

        if (!$payment) {
            return failureResponse('Payment not found.');
        }

        if ($payment->status === PaymentStatusEnums::PAID) {
            return successResponse('Wallet already credited.');
        }

        $verify = $this->paymentGateway->verify($reference);

        if (!$verify['status']) {
            $payment->update(['status' => PaymentStatusEnums::FAILED]);
            return failureResponse('Verification failed.');
        }

        DB::transaction(function () use ($payment) {
            $wallet = Wallet::find($payment->meta['wallet_id']);
            $wallet->available_balance += $payment->amount;
            $wallet->total_balance += $payment->amount;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $payment->user_id,
                'type'        => \App\Enums\WalletTransactionTypeEnums::CREDIT,
                'amount'      => $payment->amount,
                'description' => 'Wallet top-up via ShanonoPay',
                'reference'   => $payment->reference,
                'status'      => \App\Enums\WalletTransactionStatusEnums::SUCCESS,
                'method'      => \App\Enums\WalletTransactionMethodEnums::SYSTEM,
            ]);

            $payment->update([
                'status' => PaymentStatusEnums::PAID,
                'meta' => array_merge($payment->meta ?? [], [
                    'verified_at' => now(),
                ])
            ]);
        });

        $user = $payment->user;

        try {
            //Send email if available
            if (!empty($user->email)) {
                Mail::to($user->email)->send(new WalletCreditedMail($user, $payment->amount, $payment));
                Log::info('Wallet credit email sent.');
            } else {
                Log::warning("User email is missing for user ID: {$user->id}");
            }

            //Send in-app notification
            $user->notify(new WalletCreditedNotification(
                $payment->amount,
                $payment->reference,
                $payment->description ?? null
            ));
            Log::info('In-app notification sent.');

            //Send SMS if phone exists
            if (!empty($user->phone)) {
                $this->termii->sendSms($user->phone, "Your LoopFreight wallet was credited with ₦" . number_format($payment->amount, 2));
                Log::info('SMS sent to user.');
            } else {
                Log::warning("User phone number is missing for user ID: {$user->id}");
            }

            //Send WhatsApp if number exists
            if (!empty($user->whatsapp_number)) {
                $this->twilio->sendWhatsAppMessage($user->whatsapp_number, "Your LoopFreight wallet was credited with ₦" . number_format($payment->amount, 2));
                Log::info('WhatsApp message sent to user.');
            } else {
                Log::warning("User WhatsApp number is missing for user ID: {$user->id}");
            }
        } catch (\Throwable $e) {
            Log::error('Wallet credit notification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'reference' => $payment->reference ?? null,
            ]);
        }


        return successResponse('Wallet credited successfully.');
    }

    public function success()
    {
        return successResponse('Wallet payment was successful.');
    }

    public function failed()
    {
        return failureResponse('Wallet payment failed.');
    }
}
