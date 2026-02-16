<?php

namespace App\Mail;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DriverAssigned extends Mailable
{
    use Queueable, SerializesModels;

    public Delivery $delivery;
    public $driver;

    /**
     * Create a new message instance.
     */
    public function __construct(Delivery $delivery, $driver)
    {
        $this->delivery = $delivery;
        $this->driver   = $driver;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Delivery Assignment')
            ->view('emails.driver_assigned');
    }
}
