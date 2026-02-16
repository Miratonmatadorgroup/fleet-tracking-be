<?php 
namespace App\Listeners\Ratings;

use App\Events\BookRide\RidePoolDriverRated;
use App\Notifications\User\RidePoolDriverRatedNotification;

class SendRidePoolDriverRatedNotification
{
    public function handle(RidePoolDriverRated $event)
    {
        $rating = $event->rating;

        $driverUser = optional($rating->driver)->user;

        if ($driverUser) {
            $driverUser->notify(new RidePoolDriverRatedNotification($rating));
        }
    }
}