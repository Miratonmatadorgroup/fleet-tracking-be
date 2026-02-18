<?php

namespace App\Notifications\User;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;


class SubscriptionPaymentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SubscriptionPlan $subPlan,
        public User $userDetails,
        public Payment $payment,
        public ?\Carbon\Carbon $expiresAt = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $amount = number_format((float) $this->subPlan->price, 2);

        $userName = $this->userDetails->name ?? 'Customer';

        // Format payment date
        $paymentDate = $this->payment->created_at?->format('d M, Y • h:i A');

        // Load subscription relationship safely
        $this->payment->loadMissing('subscription');

        // Get expiry carbon instance
        $expiryCarbon = $this->payment->subscription?->end_date ?? $this->expiresAt;

        // Format expiry date
        $expiryDate = $expiryCarbon?->format('d M, Y') ?? '—';

        // Clean billing cycle (Monthly instead of monthly)
        $billingCycle = ucfirst($this->subPlan->billing_cycle->value);

        // Make features human readable
        $featuresArray = collect($this->subPlan->features ?? [])
            ->map(fn($feature) => ucwords(str_replace('_', ' ', $feature)))
            ->values()
            ->toArray();

        $featuresString = implode(', ', $featuresArray);

        return [
            'title' => 'Subscription Payment Successful',

            'message' =>
            "Hi {$userName}, your subscription payment of ₦{$amount} was successful.\n\n" .
                "Plan: {$this->subPlan->name}\n" .
                "Billing Cycle: {$billingCycle}\n" .
                "Payment Date: {$paymentDate}\n" .
                "Expires On: {$expiryDate}\n" .
                "Access Includes: {$featuresString}",

            // Structured clean data for frontend
            'plan' => [
                'id'            => $this->subPlan->id,
                'name'          => $this->subPlan->name,
                'billing_cycle' => $billingCycle,
                'price'         => $this->subPlan->price,
                'features'      => $featuresArray,
            ],

            'payment' => [
                'amount'     => $this->subPlan->price,
                'paid_at'    => $this->payment->created_at?->toDateTimeString(),
                'expires_at' => $expiryCarbon?->toDateTimeString(),
            ],
        ];
    }
}
