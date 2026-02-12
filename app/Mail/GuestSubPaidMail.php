<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\SubscriptionPlan;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class GuestSubPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public SubscriptionPlan $subPlan;
    public ?Payment $payment;

    public function __construct(
        User $user,
        SubscriptionPlan $subPlan,
        ?Payment $payment
    ) {
        $this->user = $user;
        $this->subPlan = $subPlan;
        $this->payment = $payment;
    }

    public function build()
    {
        return $this->subject('Subscription Payment Successful')
            ->view('emails.guest_sub_paid');
    }
}

