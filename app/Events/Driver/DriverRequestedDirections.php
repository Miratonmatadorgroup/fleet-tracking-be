<?php
namespace App\Events\Driver;



use App\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class DriverRequestedDirections
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $driver,
        public string $pickupLocation
    ) {}
}
