<?php
namespace App\Listeners\Ratings;

use App\Events\Ratings\DriverRated;
use App\Notifications\DriverRatedNotification;


class NotifyDriverOfRating
{
    public function handle(DriverRated $event): void
    {
        $driver = $event->rating->driver;

        if ($driver) {
            $driver->notify(new DriverRatedNotification($event->rating));
        }
    }
}
