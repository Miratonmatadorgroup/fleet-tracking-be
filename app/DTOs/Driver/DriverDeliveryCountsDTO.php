<?php
namespace App\DTOs\Driver;

use Illuminate\Support\Facades\Auth;
use App\Models\Driver;

class DriverDeliveryCountsDTO
{
    public Driver $driver;

    public static function fromAuth(): self
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('driver')) {
            abort(403, "Unauthorized. You must be logged in as a driver.");
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            abort(404, "Driver profile not found for this user.");
        }

        return new self($driver);
    }

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }
}
