<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Delivery;
use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubPaymentSuccessful extends Mailable
{
    use Queueable, SerializesModels;

    public $subPlan;

    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(SubscriptionPlan $subPlan, Payment $payment)
    {
        $this->subPlan = $subPlan;
        $this->payment = $payment;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Subscription Payment Successful')
            ->view('emails.subscription_payment_successful');
    }
}
