<?php 
namespace App\Events\Driver;

use App\Models\User;
use App\Models\RidePool;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RideStartedEvent
{
    use Dispatchable, SerializesModels;

    public RidePool $ride;
    public User $driver;

    public function __construct(RidePool $ride, User $driver)
    {
        $this->ride = $ride;
        $this->driver = $driver;
    }
}