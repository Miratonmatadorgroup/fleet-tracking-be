<?php
namespace App\Mail;

use App\Models\Delivery;
use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryAssignedToDriver extends Mailable
{
    use Queueable, SerializesModels;

    public Delivery $delivery;
    public Driver $driver;

    public function __construct(Delivery $delivery, Driver $driver)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
    }

    public function build()
    {
        return $this->subject('New Delivery Assignment')
            ->view('emails.delivery_assigned_to_driver');
    }
}
