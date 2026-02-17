<?php

namespace App\Notifications\User;

use App\Models\SubscriptionPlan;
use Illuminate\Notifications\Notification;
use Illuminate\Bus\Queueable;


class SubscriptionAutoRenewFailedNotification extends Notification
{
    use Queueable;
    public function __construct(public SubscriptionPlan $subPlan) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $amount = number_format($this->subPlan->price, 2);

        return [
            'title' => 'Subscription Auto-Renew Failed',
            'message' =>
            "Your subscription auto-renewal failed due to insufficient balance.\n\n" .
                "Plan: {$this->subPlan->name}\n" .
                "Amount Required: ₦{$amount}\n\n" .
                "Please fund your wallet to continue enjoying premium features.",
        ];
    }
}
