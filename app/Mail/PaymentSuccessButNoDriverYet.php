<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessButNoDriverYet extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;

    public $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Delivery $delivery, Payment $payment)
    {
        $this->delivery = $delivery;
        $this->payment = $payment;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Payment Successful - Awaiting Driver Assignment')
            ->view('emails.payment_success_but_no_driver_yet');
    }
}
