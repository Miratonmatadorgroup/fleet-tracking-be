<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PayoutInitiatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payout $payout) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => sprintf(
                "Your payout of %s %.2f to %s account ending in %s has been initiated. Status: %s.",
                $this->payout->currency,
                $this->payout->amount,
                $this->payout->bank_name,
                substr($this->payout->account_number, -4),
                ucfirst($this->payout->status->value) 
            ),
            'payout_id' => $this->payout->id,
            'status' => $this->payout->status->value,
            'amount' => $this->payout->amount,
            'currency' => $this->payout->currency,
            'bank_name' => $this->payout->bank_name,
            'account_last4' => substr($this->payout->account_number, -4),
        ];
    }
}
