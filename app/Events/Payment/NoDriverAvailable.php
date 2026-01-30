<?php
namespace App\Events\Payment;

use App\Models\Delivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoDriverAvailable
{
    use Dispatchable, SerializesModels;

    public Delivery $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }
}

