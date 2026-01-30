<?php
namespace App\Events\Payment;

use App\Models\Driver;
use App\Models\Delivery;
use App\Models\TransportMode;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class DriverAssignedToDelivery
{
    use Dispatchable, SerializesModels;

    public Delivery $delivery;
    public Driver $driver;
    public TransportMode $transport;

    public function __construct(Delivery $delivery, Driver $driver, TransportMode $transport)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
        $this->transport = $transport;
    }
}
