<?php

namespace App\Mail;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Mail\Mailable;


class DriverUnassignedFromDelivery extends Mailable
{
    public function __construct(public Driver $driver, public Delivery $delivery) {}

    public function build()
    {
        return $this->view('emails.driver.delivery_unassigned_driver')
            ->subject(' Delivery Unassigned')
            ->with([
                'driver' => $this->driver,
                'delivery' => $this->delivery,
            ]);
    }
}
