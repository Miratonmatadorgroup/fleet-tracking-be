<?php

namespace App\Notifications\User;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PayoutCompletedNotification extends Notification
{
    use Queueable;

    public Payout $payout;

    public function __construct(Payout $payout)
    {
        $this->payout = $payout;
    }

    /**
     * Notification channels
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Store notification in database
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title'   => 'Payout Completed',
            'message' => sprintf(
                'Your payout of ₦%s to %s (%s) is complete and successful.',
                number_format((float) $this->payout->amount, 2),
                $this->payout->bank_name,
                $this->payout->account_number
            ),

            'payout_id' => $this->payout->id,
            'amount'    => $this->payout->amount,
            'currency'  => $this->payout->currency,
            'status'    => $this->payout->status->value,

            'provider_reference' => $this->payout->provider_reference,
        ];
    }
}
