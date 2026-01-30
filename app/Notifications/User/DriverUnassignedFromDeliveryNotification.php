<?php
namespace App\Notifications\User;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverUnassignedFromDeliveryNotification extends Notification
{
    use Queueable;

    public Driver $driver;
    public Delivery $delivery;

    public function __construct(Driver $driver, Delivery $delivery)
    {
        $this->driver = $driver;
        $this->delivery = $delivery;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Delivery Unassignment',
            'message' => "Hi {$this->driver->name}, the delivery with tracking number {$this->delivery->tracking_number} has been reassigned.",
            'driver_id' => $this->driver->id,
            'delivery_id' => $this->delivery->id,
            'tracking_number' => $this->delivery->tracking_number,
        ];
    }
}
