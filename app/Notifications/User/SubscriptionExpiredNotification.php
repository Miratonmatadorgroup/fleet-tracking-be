<?php

namespace App\Notifications\User;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification
{
    use Queueable;
    public function __construct(public Subscription $subscription) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $amount = number_format($this->subscription->plan->price, 2);

        return [
            'title' => 'Subscription Expired',
            'message' =>
            "Your subscription has expired.\n\n" .
                "Plan: {$this->subscription->plan->name}\n" .
                "Amount Required: ₦{$amount}\n\n" .
                "Please renew your subscription to continue enjoying premium features.",
        ];
    }
}
