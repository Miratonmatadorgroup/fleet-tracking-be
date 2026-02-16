<?php

namespace App\Notifications\User;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Delivery $delivery) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $date = \Carbon\Carbon::parse($this->delivery->delivery_date)->format('d M, Y');
        $time = \Carbon\Carbon::parse($this->delivery->delivery_time)->format('H:i');

        return [
            'title' => 'New Delivery Assigned',
            'message' => "New booking from PickUp:{$this->delivery->sender_name} to DropOff:{$this->delivery->receiver_name} to be delivered on {$date} at {$time}.",

            'delivery_id' => $this->delivery->id,
            'tracking_number' => $this->delivery->tracking_number,

            // Contacts
            'sender_name' => $this->delivery->sender_name,
            'sender_phone' => $this->delivery->sender_phone,
            'receiver_name' => $this->delivery->receiver_name,
            'receiver_phone' => $this->delivery->receiver_phone,

            // Delivery info
            'pickup_location' => $this->delivery->pickup_location,
            'dropoff_location' => $this->delivery->dropoff_location,
            'delivery_date' => $this->delivery->delivery_date,
            'delivery_time' => $this->delivery->delivery_time,
            'package_description' => $this->delivery->package_description,
            'package_weight' => $this->delivery->package_weight,
            'distance_km' => $this->delivery->distance_km,
            'duration_minutes' => $this->delivery->duration_minutes,

            'time' => now()->toDateTimeString(),
        ];
    }
}
