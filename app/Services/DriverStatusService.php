<?php


namespace App\Services;

use App\Models\Driver;
use App\Enums\DriverStatusEnums;

class DriverStatusService
{
    public static function makeAvailable(Driver $driver): void
    {
        $driver->update(['status' => DriverStatusEnums::AVAILABLE]);
    }

    public static function makeUnavailable(Driver $driver): void
    {
        $driver->update(['status' => DriverStatusEnums::UNAVAILABLE]);
    }
}

