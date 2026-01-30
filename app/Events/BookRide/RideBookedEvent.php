<?php
namespace App\Events\BookRide;

use App\Models\RidePool;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RideBookedEvent
{
    use Dispatchable, SerializesModels;

    public RidePool $ride;

    public function __construct(RidePool $ride)
    {
        $this->ride = $ride;
    }
}
