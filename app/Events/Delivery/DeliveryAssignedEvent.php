<?php
namespace App\Events\Delivery;


use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Queue\SerializesModels;
use App\Enums\DeliveryAssignmentLogsEnums;
use Illuminate\Foundation\Events\Dispatchable;

class DeliveryAssignedEvent
{
    use Dispatchable, SerializesModels;

    public Delivery $delivery;
    public ?Driver $driver;
    public DeliveryAssignmentLogsEnums $status;

    public function __construct(Delivery $delivery, ?Driver $driver, DeliveryAssignmentLogsEnums $status)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
        $this->status = $status;
    }
}
