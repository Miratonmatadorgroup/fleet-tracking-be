<?php

namespace App\Events\Driver;

use App\Models\User;
use App\Models\RidePool;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RideAcceptedEvent
{
    use Dispatchable, SerializesModels;

    public RidePool $ride;
    public User $driver;
    public array $broadcastData;

    public function __construct(RidePool $ride, User $driver)
    {
        $this->ride = $ride;
        $this->driver = $driver;

        $this->broadcastData = [
            'eta_minutes'   => $ride->eta_minutes,
            'eta_timestamp' => $ride->eta_timestamp,
        ];
    }
}
