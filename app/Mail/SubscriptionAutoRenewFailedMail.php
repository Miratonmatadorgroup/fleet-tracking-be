<?php

namespace App\Mail;

use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionAutoRenewFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SubscriptionPlan $subPlan) {}

    public function build()
    {
        return $this->subject('Subscription Auto-Renew Failed')
            ->view('emails.subscription_auto_renew_failed');
    }
}
