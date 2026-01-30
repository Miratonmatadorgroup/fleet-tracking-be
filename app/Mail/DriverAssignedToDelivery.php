<?php

namespace App\Mail;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Mail\Mailable;


class DriverAssignedToDelivery extends Mailable
{
    public function __construct(public Driver $driver, public Delivery $delivery) {}

    public function build()
    {
        return $this->view('emails.driver.delivery_assigned_driver')
            ->subject('New Delivery Assigned')
            ->with([
                'driver' => $this->driver,
                'delivery' => $this->delivery,
            ]);
    }
}
