<?php

namespace App\Actions\Investor;

use Exception;
use App\Models\Wallet;
use App\Models\Payment;
use App\Models\Investor;
use App\Models\InvestmentPlan;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\PaymentStatusEnums;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReinvestmentSuccessMail;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Notifications\ReInvestmentPaymentSuccessfulNotification;

class PayReinvestmentFromWalletAction
{
    public function __construct(protected TwilioService $twilio, protected TermiiService $termii) {}

    public function execute(Investor $investor, InvestmentPlan $plan, string $pin): Payment
    {

        $user = Auth::user();
        if (! $user) {
            throw new \Exception("Unauthorized.");
        }
        app(\App\Services\TransactionPinService::class)->checkPin($user, $pin);

        return DB::transaction(function () use ($investor, $plan) {
            $wallet = Wallet::where('user_id', $investor->user_id)->lockForUpdate()->firstOrFail();

            $amount = (float) $plan->amount;

            if ($wallet->available_balance < $amount) {
                throw new Exception("Insufficient wallet balance for reinvestment.");
            }

            // Deduct wallet balance
            $wallet->available_balance -= $amount;
            $wallet->total_balance -= $amount;
            $wallet->save();

            // Log wallet transaction
            $walletTransaction = WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $investor->user_id,
                'type'        => WalletTransactionTypeEnums::DEBIT->value,
                'amount'      => $amount,
                'description' => "Reinvestment for Plan #{$plan->id}",
                'reference'   => strtoupper(uniqid("REINV-")),
                'status'      => WalletTransactionStatusEnums::SUCCESS->value,
                'method'      => WalletTransactionMethodEnums::WALLET->value,
            ]);

            // Create payment record
            $payment = Payment::create([
                'user_id'   => $investor->user_id,
                'status'    => PaymentStatusEnums::PAID,
                'reference' => $walletTransaction->reference,
                'amount'    => $amount,
                'currency'  => $wallet->currency,
                'gateway'   => 'wallet',
                'meta'      => [
                    'investor_id'        => $investor->id,
                    'investment_plan_id' => $plan->id,
                    'reinvestment'       => true,
                ],
            ]);

            // Update investor’s investment amount
            $investor->update([
                'investment_amount' => $investor->investment_amount + $amount,
                'status' => \App\Enums\InvestorStatusEnums::ACTIVE,
            ]);

            // Send notifications
            try {
                $user = $investor->user;
                if ($user->email) {
                    Mail::to($user->email)->send(new ReinvestmentSuccessMail($user, $amount));
                }

                if (!empty($user->phone)) {
                    $message = "Hi {$user->name}, your LoopFreight reinvestment of ₦{$amount} was successful. Ref: {$payment->reference}";
                    $this->termii->sendSms($user->phone, $message);
                    $this->twilio->sendWhatsAppMessage($user->whatsapp_number, $message);
                }

                $user->notify(new ReInvestmentPaymentSuccessfulNotification(
                    (string) $amount,
                    $payment->reference
                ));
            } catch (\Throwable $e) {
                report($e);
            }

            return $payment;
        });
    }
}
