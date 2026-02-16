<?php
namespace App\Listeners\Delivery;


use App\Models\DeliveryAssignmentLog;
use App\Events\Delivery\DeliveryAssignedEvent;

class LogDeliveryAssignment
{
    public function handle(DeliveryAssignedEvent $event): void
    {
        DeliveryAssignmentLog::create([
            'delivery_id' => $event->delivery->id,
            'driver_id'   => $event->driver?->id,
            'status'      => $event->status->value,
            'attempted_at'=> now(),
        ]);
    }
}
