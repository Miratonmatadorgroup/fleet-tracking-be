<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CableTvPurchaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $decoder,
        public string $provider,
        public string $package,
        public string $reference,
        public string $status,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->status === 'success'
                ? 'Cable TV Subscription Successful'
                : 'Cable TV Subscription Processing',

            'message' => $this->status === 'success'
                ? "₦" . number_format($this->amount, 2) .
                " {$this->provider} {$this->package} subscription for decoder {$this->decoder} was successful."
                : "Your {$this->provider} {$this->package} subscription for decoder {$this->decoder} is being processed.",

            'decoder'   => $this->decoder,
            'provider'  => $this->provider,
            'package'   => $this->package,
            'amount'    => $this->amount,
            'reference' => $this->reference,
            'status'    => $this->status,
            'time'      => now(),
        ];
    }
}
