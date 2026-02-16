<?php

namespace App\Notifications\Admin;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryMarkedCompletedNotification extends Notification
{
    use Queueable;

    public Delivery $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $driverName = $this->delivery->driver?->user?->name
            ?? $this->delivery->driver?->name
            ?? 'N/A';
        return [
            'title' => 'Delivery Completed by Admin',
            'message' => "Delivery with (Tracking No: {$this->delivery->tracking_number}) has been marked as completed and commissions released to driver {$driverName}.",
            'delivery_id' => $this->delivery->id,
            'status' => $this->delivery->status,
            'time' => now()->toDateTimeString(),
        ];
    }
}
