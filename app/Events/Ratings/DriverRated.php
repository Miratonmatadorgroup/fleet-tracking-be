<?php
namespace App\Events\Ratings;

use App\Models\DriverRating;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class DriverRated
{
    use Dispatchable, SerializesModels;

    public function __construct(public DriverRating $rating) {}
}
