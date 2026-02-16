<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DataPurchaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $phone,
        public string $provider,
        public string $reference,
        public string $units
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'   => 'Data Purchase Successful',

            'message' =>
                "({$this->provider}) {$this->units} data sent to {$this->phone}  "
                . "for ₦" . number_format($this->amount, 2) . ".",

            'reference' => $this->reference,
            'amount'    => $this->amount,
            'provider'  => $this->provider,
            'phone'     => $this->phone,
            'units'     => $this->units,

            'time' => now()->toDateTimeString(),
        ];
    }
}
