<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NoAvailableDriverNotification extends Notification
{
    public $delivery;

    public function __construct($delivery)
    {
        $this->delivery = $delivery;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'No Available Driver Found',
            'message' => "No available driver found for delivery with tracking number: {$this->delivery->tracking_number}.",
            'delivery_id' => $this->delivery->id,
            'pickup_location' => $this->delivery->pickup_location,
            'dropoff_location' => $this->delivery->dropoff_location,
            'delivery_date' => $this->delivery->delivery_date,
            'delivery_time' => $this->delivery->delivery_time,
        ];
    }
}
