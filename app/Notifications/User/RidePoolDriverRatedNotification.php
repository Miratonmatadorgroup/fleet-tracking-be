<?php

namespace App\Notifications\User;

use App\Models\DriverRating;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RidePoolDriverRatedNotification extends Notification
{
    use Queueable;

    public DriverRating $rating;

    public function __construct(DriverRating $rating)
    {
        $this->rating = $rating;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'   => 'You received a new rating!',
            'message' => "A customer rated you {$this->rating->rating} star(s).",
            'rating_id' => $this->rating->id,
            'ride_pool_id' => $this->rating->ride_pool_id,
            'customer_id' => $this->rating->customer_id,
            'status'  => 'driver_rated',
        ];
    }
}
