<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryMarkedAsDelivered extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;
    public $driver;

    /**
     * Create a new message instance.
     */
    public function __construct($delivery, $driver)
    {
        $this->delivery = $delivery;
        $this->driver   = $driver;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Delivery has been Delivered')
            ->view('emails.delivery_marked_as_delivered')
            ->with([
                'delivery'  => $this->delivery,
                'driver'    => $this->driver,
                'transport' => $this->delivery->transport ?? null,
            ]);
    }
}
