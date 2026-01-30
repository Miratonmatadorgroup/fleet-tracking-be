<?php
namespace App\Events\Delivery;

use App\Models\Delivery;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;


class DeliveryCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Delivery $delivery) {}
}
