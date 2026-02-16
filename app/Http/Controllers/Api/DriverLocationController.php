<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\DriverLocation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DriverLocationController extends Controller
{
    public function update(Request $request)
    {
        try {
            $user = Auth::user();

            // Check if the user has the 'driver' role
            if (!$user->hasRole('driver')) {
                return failureResponse('You are not authorized to perform this action.', 403);
            }

            // Validate input
            $request->validate([
                'latitude'     => 'required|numeric',
                'longitude'    => 'required|numeric',
                'delivery_id'  => 'nullable|uuid|exists:deliveries,id',
            ]);

            $driver = $user->driver;
            $historyLimit = 30; // keep last 30 entries

            // Fetch last location for this delivery
            $lastLocation = DriverLocation::where('driver_id', $driver->id)
                ->where('delivery_id', $request->delivery_id)
                ->latest()
                ->first();

            // If last location exists, calculate distance
            if ($lastLocation) {
                $distance = $this->calculateDistance(
                    $lastLocation->latitude,
                    $lastLocation->longitude,
                    $request->latitude,
                    $request->longitude
                );

                // If driver moved less than 30 meters, skip saving
                if ($distance < 30) {
                    // Still return recent history for frontend convenience
                    $history = DriverLocation::where('driver_id', $driver->id)
                        ->where('delivery_id', $request->delivery_id)
                        ->latest()
                        ->take($historyLimit)
                        ->get()
                        ->reverse()
                        ->values();

                    return successResponse('Location unchanged (below 30m threshold)', [
                        'latest'  => $lastLocation,
                        'history' => $history,
                    ]);
                }
            }

            // Save new location
            $location = DriverLocation::create([
                'driver_id'    => $driver->id,
                'delivery_id'  => $request->delivery_id,
                'latitude'     => $request->latitude,
                'longitude'    => $request->longitude,
            ]);

            // Clean up old records beyond $historyLimit
            $this->cleanupOldLocations($driver->id, $request->delivery_id, $historyLimit);

            //most recent 30 entries
            $history = DriverLocation::where('driver_id', $driver->id)
                ->where('delivery_id', $request->delivery_id)
                ->latest()
                ->take($historyLimit)
                ->get()
                ->reverse()
                ->values();

            return successResponse('Location updated successfully', [
                'latest'  => $location,
                'history' => $history,
            ]);
        } catch (\Throwable $th) {
            return failureResponse('An error occurred while updating location.', 500, 'location_update_error', $th);
        }
    }

    /**
     * Delete old records beyond limit, in chunks
     */
    private function cleanupOldLocations($driverId, $deliveryId, $historyLimit)
    {
        $idsToKeep = DriverLocation::where('driver_id', $driverId)
            ->where('delivery_id', $deliveryId)
            ->latest()
            ->take($historyLimit)
            ->pluck('id');

        DriverLocation::where('driver_id', $driverId)
            ->where('delivery_id', $deliveryId)
            ->whereNotIn('id', $idsToKeep)
            ->orderBy('id')
            ->chunkById(500, function ($oldLocations) {
                DriverLocation::whereIn('id', $oldLocations->pluck('id'))->delete();
            });
    }



    /**
     * Haversine Formula to calculate distance between two lat/lng in meters
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // in meters
    }


    public function getDeliveryLocation(string $deliveryId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return failureResponse('Unauthorized.', 401);
            }
            
            $location = DriverLocation::where('delivery_id', $deliveryId)
                ->latest()
                ->first();

            if (!$location) {
                return failureResponse("No location found yet", 404);
            }

            return successResponse("Driver location fetched", $location);
        } catch (\Throwable $th) {
            return failureResponse('An error occurred while fetching driver location.', 500, 'location_fetch_error', $th);
        }
    }
}
