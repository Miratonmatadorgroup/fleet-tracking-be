<?php

namespace App\Actions\Investor;

use Exception;
use App\Models\Wallet;
use App\Models\Payment;
use App\Models\Investor;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\PaymentStatusEnums;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Mail\InvestmentPaymentSuccessfulMail;

class PayInvestmentFromWalletAction
{
    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {}

    public function execute(Investor $investor, string $pin): Payment
    {

        $user = Auth::user();
        if (! $user) {
            throw new \Exception("Unauthorized.");
        }

        app(\App\Services\TransactionPinService::class)->checkPin($user, $pin);

        return DB::transaction(function () use ($investor) {
            $wallet = Wallet::where('user_id', $investor->user_id)->lockForUpdate()->firstOrFail();

            $amount = $investor->investment_amount;

            if ($wallet->available_balance < $amount) {
                throw new Exception("Insufficient wallet balance.");
            }

            // Deduct wallet balance
            $wallet->available_balance -= $amount;
            $wallet->total_balance -= $amount;
            $wallet->save();

            // Create wallet transaction
            $walletTransaction = WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $investor->user_id,
                'type'        => WalletTransactionTypeEnums::DEBIT->value,
                'amount'      => $amount,
                'description' => "Investment payment for Plan #{$investor->investment_plan_id}",
                'reference'   => strtoupper(uniqid("INV-")),
                'status'      => WalletTransactionStatusEnums::SUCCESS->value,
                'method'      => WalletTransactionMethodEnums::WALLET->value,
            ]);

            // Create payment record
            $payment = Payment::create([
                'user_id'        => $investor->user_id,
                'status'         => PaymentStatusEnums::PAID,
                'reference'      => $walletTransaction->reference,
                'amount'         => $amount,
                'currency'       => $wallet->currency,
                'final_price'    => $amount,
                'original_price' => $amount,
                'subsidy_amount' => 0,
                'gateway'        => 'wallet',
                'meta'           => ['investment_id' => $investor->id],
            ]);

            // Update investor status
            $investor->update([
                'status'             => \App\Enums\InvestorStatusEnums::ACTIVE
            ]);

            // Send notifications
            try {
                if ($investor->user->email) {
                    Mail::to($investor->user->email)
                        ->send(new InvestmentPaymentSuccessfulMail($investor));
                }

                if (!empty($investor->phone)) {
                    $this->termii->sendSms(
                        $investor->phone,
                        "Hi {$investor->full_name}, your LoopFreight investment payment of ₦{$amount} was successful. Ref: {$payment->reference}"
                    );
                }

                if (!empty($investor->whatsapp_number)) {
                    $this->twilio->sendWhatsAppMessage(
                        $investor->whatsapp_number,
                        "Investment payment successful!\nAmount: ₦{$amount}\nReference: {$payment->reference}"
                    );
                }

                if ($investor->user) {
                    $investor->user->notify(
                        new \App\Notifications\User\InvestmentPaymentSuccessfulNotification(
                            (string) $amount,
                            $payment->reference
                        )
                    );
                }
            } catch (\Throwable $e) {
                //Don’t break the transaction if notification fails
                report($e);
            }

            return $payment;
        });
    }
}
