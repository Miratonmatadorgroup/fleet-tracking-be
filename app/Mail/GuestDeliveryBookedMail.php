<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestDeliveryBookedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $messageText;

    /**
     * Create a new message instance.
     */
    public function __construct(string $messageText)
    {
        $this->messageText = $messageText;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Delivery Booking Confirmation')
            ->view('emails.guest_delivery_booked')
            ->with([
                'messageText' => $this->messageText,
            ]);
    }
}
