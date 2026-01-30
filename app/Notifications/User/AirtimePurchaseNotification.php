<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AirtimePurchaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $phone,
        public string $provider,
        public string $reference
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'   => 'Airtime Purchase Successful',
            'message' => "₦" . number_format($this->amount, 2) .
                " airtime sent to {$this->phone} ({$this->provider}).",

            'reference' => $this->reference,
            'amount'    => $this->amount,
            'provider'  => $this->provider,
            'phone'     => $this->phone,

            'time' => now()->toDateTimeString(),
        ];
    }
}
