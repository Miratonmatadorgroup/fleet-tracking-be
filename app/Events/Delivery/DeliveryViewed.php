<?php
namespace App\Events\Delivery;


use App\Models\Delivery;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class DeliveryViewed
{
    use Dispatchable, SerializesModels;

    public Delivery $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }
}
