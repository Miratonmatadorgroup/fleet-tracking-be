<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvestmentPaymentSuccessfulNotification extends Notification
{
    use Queueable;

    protected string $amount;
    protected string $reference;

    public function __construct(string $amount, string $reference)
    {
        $this->amount = $amount;
        $this->reference = $reference;
    }

    public function via(object $notifiable): array
    {
        return ['database']; // only in-app
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Investment Payment Successful',
            'message' => "Your investment payment of ₦{$this->amount} was successful. Reference: {$this->reference}",
            'amount' => $this->amount,
            'reference' => $this->reference,
            'time' => now()->toDateTimeString(),
        ];
    }
}
