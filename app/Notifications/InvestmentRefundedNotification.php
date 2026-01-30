<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvestmentRefundedNotification extends Notification
{
    use Queueable;

    protected $investor;

    public function __construct($investor)
    {
        $this->investor = $investor;
    }

    public function via($notifiable)
    {
        return ['database']; // or ['mail', 'database']
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Refund Processed',
            'message' => "Your investment withdrawal of ₦{$this->investor->investment_amount} has been refunded.",
            'refund_note' => $this->investor->refund_note,
            'refunded_at' => $this->investor->refunded_at,
        ];
    }
}

