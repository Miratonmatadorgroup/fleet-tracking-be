<?php

namespace App;

use App\Enums\PaymentStatusEnums;
use App\Enums\SubscriptionStatusEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Enums\WalletTransactionTypeEnums;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoRenewSubscriptionAction
{
    public function execute(Subscription $expiredSubscription): bool
    {
        $user = $expiredSubscription->user;
        $plan = $expiredSubscription->plan;

        $renewed = false; // Always declare first

        // If no wallet → auto renew fails
        if (!$user || !$user->wallet) {

            app(NotificationService::class)
                ->sendAutoRenewFailed($user, $plan);

            return false;
        }

        $amount = (float) $plan->price;

        DB::transaction(function () use (
            $user,
            $plan,
            $amount,
            &$renewed,
            $expiredSubscription
        ) {

            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            // Wallet not found or insufficient funds
            if (!$wallet || $wallet->available_balance < $amount) {
                return;
            }

            $reference = 'SUB-RENEW-' . Str::upper(Str::random(10));

            $wallet->decrement('available_balance', $amount);

            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $user->id,
                'type'        => WalletTransactionTypeEnums::DEBIT,
                'amount'      => $amount,
                'description' => "Subscription auto-renewal for {$plan->name}",
                'reference'   => $reference,
                'status'      => WalletTransactionStatusEnums::SUCCESS,
                'method'      => WalletTransactionMethodEnums::WALLET,
            ]);

            $startDate = now();

            $endDate = match ($plan->billing_cycle->value) {
                'monthly'   => now()->addMonth(),
                'quarterly' => now()->addMonths(3),
                'yearly'    => now()->addYear(),
                default     => now()->addMonth(),
            };

            $subscription = Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'status'         => SubscriptionStatusEnums::ACTIVE,
                'auto_renew'     => $expiredSubscription->auto_renew,
                'payment_method' => 'wallet',
            ]);

            Payment::create([
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
                'amount'          => $amount,
                'status'          => PaymentStatusEnums::PAID,
                'gateway'         => 'wallet',
                'reference'       => $reference,
                'paid_at'         => now(),
                'expires_at'      => $endDate,
            ]);

            $renewed = true;
        });

        // Send notification AFTER transaction
        if ($renewed) {
            app(NotificationService::class)
                ->sendAutoRenewSuccess($user, $plan);
        } else {
            app(NotificationService::class)
                ->sendAutoRenewFailed($user, $plan);
        }

        return $renewed;
    }
}
