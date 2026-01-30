<?php 
namespace App\Events\BookRide;

use App\Models\DriverRating;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RidePoolDriverRated
{
    use Dispatchable, SerializesModels;

    public DriverRating $rating;

    /**
     * Create a new event instance.
     *
     * @param DriverRating $rating
     */
    public function __construct(DriverRating $rating)
    {
        $this->rating = $rating;
    }
}
