<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ElectricityPurchaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $phone,
        public string $provider,
        public string $meter,
        public string $vendtype
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'   => 'Electricity Purchase Successful',
            'message' => "₦" . number_format($this->amount, 2) .
                " airtime sent to {$this->meter} ({$this->provider}).",

            'meter' => $this->meter,
            'amount'    => $this->amount,
            'provider'  => $this->provider,
            'phone'     => $this->phone,
            'vendtype'     => $this->vendtype,


            'time' => now()->toDateTimeString(),
        ];
    }
}
