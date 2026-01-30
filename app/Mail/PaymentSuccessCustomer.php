<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public Delivery $delivery;
    public Payment $payment;

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
        return $this->subject('Payment Confirmation & Delivery Booking Successful')
            ->view('emails.payment_success_customer');
    }
}

