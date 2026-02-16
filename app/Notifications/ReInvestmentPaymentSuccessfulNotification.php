<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReInvestmentPaymentSuccessfulNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $amount;
    protected string $reference;

    public function __construct(string $amount, string $reference)
    {
        $this->amount = $amount;
        $this->reference = $reference;
    }

    public function via($notifiable)
    {
        return ['database']; // can also include 'mail', 'broadcast', etc. if needed
    }

    public function toArray($notifiable)
    {
        return [
            'title'   => 'Reinvestment Payment Successful',
            'message' => "Your reinvestment of ₦{$this->amount} has been successfully processed. Reference: {$this->reference}",
            'amount'  => $this->amount,
            'reference' => $this->reference,
        ];
    }
}
