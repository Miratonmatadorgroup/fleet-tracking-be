<?php
namespace App\Notifications\User;

use App\Models\Delivery;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryMarkedAsDeliveredNotification extends Notification
{
    use Queueable;

    public function __construct(public Delivery $delivery) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Delivery Marked as Delivered',
            'message' => "Your delivery (Tracking No: {$this->delivery->tracking_number}) has been marked as delivered by {$this->delivery->driver->name}. Please contact the receiver and confirm completion from your dashboard.",
            'delivery_id' => $this->delivery->id,
            'tracking_number' => $this->delivery->tracking_number,
            'driver_name' => $this->delivery->driver->name,
            'time' => now()->toDateTimeString(),
        ];
    }
}
