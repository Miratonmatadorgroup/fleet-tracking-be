<?php

namespace App\Notifications\User;

use App\Models\User;
use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RideStartedNotification extends Notification
{
    use Queueable;

    public RidePool $ride;
    public User $driver;

    public function __construct(RidePool $ride, User $driver)
    {
        $this->ride = $ride;
        $this->driver = $driver;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Ride Started',
            'message' => "Your ride with {$this->driver->name} has begun.",
            'ride_id' => $this->ride->id,
            'status' => 'ride_started',
        ];
    }
}
