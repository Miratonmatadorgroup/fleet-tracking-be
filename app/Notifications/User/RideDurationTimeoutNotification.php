<?php

namespace App\Notifications\User;

use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RideDurationTimeoutNotification extends Notification
{
    use Queueable;

    public RidePool $ride;

    public function __construct(RidePool $ride)
    {
        $this->ride = $ride;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Ride Ended – Duration Exhausted',
            'message' => "Your trip duration has been exhausted. The ride has been automatically ended. Please rebook if you need more time.",
            'ride_id' => $this->ride->id,
            'status' => 'ride_timeout',
        ];
    }
}