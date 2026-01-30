<?php

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\DriverStatusService;

class DriverStatusController extends Controller
{
    /**
     * Mark driver as available.
     */
    public function makeAvailable($driverId): JsonResponse
    {
        $driver = Driver::find($driverId);

        if (! $driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        DriverStatusService::makeAvailable($driver);

        return response()->json([
            'message' => 'Driver marked as available',
            'driver' => $driver,
        ]);
    }

    /**
     * Mark driver as unavailable.
     */
    public function makeUnavailable($driverId): JsonResponse
    {
        $driver = Driver::find($driverId);

        if (! $driver) {
            return response()->json(['message' => 'Driver not found'], 404);
        }

        DriverStatusService::makeUnavailable($driver);

        return response()->json([
            'message' => 'Driver marked as unavailable',
            'driver' => $driver,
        ]);
    }
}
