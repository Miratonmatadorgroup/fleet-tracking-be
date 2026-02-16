<?php
namespace App\Events\Delivery;


use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryCancelledEvent
{
    use Dispatchable, SerializesModels;

    public string $deliveryId;

    public function __construct(string $deliveryId)
    {
        $this->deliveryId = $deliveryId;
    }
}
