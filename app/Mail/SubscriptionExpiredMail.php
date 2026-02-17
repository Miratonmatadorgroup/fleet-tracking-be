<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

use Illuminate\Queue\SerializesModels;

class SubscriptionExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function build()
    {
        return $this->subject('Subscription Expired')
            ->view('emails.subscription_expiration')
            ->with([
                'subscription' => $this->subscription,
            ]);
    }
}
