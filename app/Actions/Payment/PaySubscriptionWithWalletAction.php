<?php

namespace App\Actions\Payment;


use App\Models\Driver;
use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Support\Str;
use App\Models\Subscription;
use App\Models\TransportMode;
use App\Services\DriverService;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Models\SubscriptionPlan;
use App\Enums\PaymentStatusEnums;
use App\Models\WalletTransaction;
use App\Enums\DeliveryStatusEnums;
use App\Mail\SubPaymentSuccessful;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\DeliveryAssignedToUser;
use App\Services\WalletGuardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\ExternalBankService;
use App\Services\NotificationService;
use App\Enums\SubscriptionStatusEnums;
use App\Mail\DeliveryAssignedToDriver;
use App\Services\TransactionPinService;
use App\Services\WalletPurchaseService;
use App\Enums\WalletTransactionStatusEnums;
use App\DTOs\Payment\PaySubscriptionWithWalletDTO;


class PaySubscriptionWithWalletAction
{
    public function __construct(
        private WalletGuardService $guard,
        private WalletPurchaseService $walletPurchase,
        private ExternalBankService $bank,
        private NotificationService $notificationService
    ) {}

    public function execute($dto): Subscription
    {
        $user = Auth::user();

        /** @var SubscriptionPlan $plan */
        $plan = SubscriptionPlan::where('id', $dto->subscription_plan_id)
            ->where('is_active', true)
            ->firstOrFail();

        // Ensure user_type matches plan
        if ($user->user_type->value !== $plan->user_type->value) {
            throw new \Exception(
                "You cannot subscribe to this plan because it is only for {$plan->user_type->value} users."
            );
        }

        //Prevent overlapping active subscriptions
        $activeSubscription = Subscription::where('user_id', $user->id)
            ->where('end_date', '>=', now())
            ->active()
            ->first();

        if ($activeSubscription) {
            throw new \Exception(
                "You already have an active subscription (Plan: {$activeSubscription->plan->name}) that expires on {$activeSubscription->end_date->format('Y-m-d')}."
            );
        }

        // Prevent double active subscription (same plan)
        $existing = Subscription::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->active()
            ->first();

        if ($existing) {
            throw new \Exception('You already have an active subscription for this plan.');
        }


        // PIN validation
        app(TransactionPinService::class)->checkPin($user, $dto->pin);

        $wallet = $user->wallet;
        $amount = (float) $plan->price;

        // Wallet & external guards
        $this->guard->ensureCanSpend($user, $amount);
        $this->guard->ensureExternalAccountActive($wallet, $this->bank);
        $this->guard->ensureMerchantLiquidity($this->bank, $amount);

        // Wallet debit
        $walletTransaction = $this->walletPurchase->process(
            user: $user,
            wallet: $wallet,
            amount: $amount,
            method: 'wallet',
            providerCallback: fn(string $reference) => [
                [
                    'responseCode' => 200,
                    'reference'    => $reference,
                ],
            ],
            meta: [
                'purpose' => 'subscription_payment',
                'plan_id' => $plan->id,
            ]
        );

        // Dates
        $startDate = now();
        $endDate = match ($plan->billing_cycle->value) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(4),
            'yearly' => now()->addYear(),
            default => now()->addMonth(),
        };

        // Persist subscription + payment
        DB::transaction(function () use (
            $user,
            $plan,
            $walletTransaction,
            $startDate,
            $endDate,
            &$subscription,
            &$payment // capture payment
        ) {
            $subscription = Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'asset_id'       => $user->asset_id ?? null,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'status'         => SubscriptionStatusEnums::ACTIVE,
                'auto_renew'     => true,
                'payment_method' => 'wallet',
            ]);

            // Assign to $payment variable
            $payment = Payment::create([
                'user_id'        => $user->id,
                'subscription_id' => $subscription->id,
                'amount'         => $plan->price,
                'status'         => PaymentStatusEnums::PAID,
                'gateway'        => 'wallet',
                'reference'      => $walletTransaction->reference,
                'paid_at'        => now(),
                'expires_at'     => $endDate,
            ]);
        });


        $subscription = Subscription::with(['plan', 'payments'])
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        // Notify user
        $this->notificationService
            ->notifyUser($user, $plan, $payment);

        return $subscription;
    }
}
