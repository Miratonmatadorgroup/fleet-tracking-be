<?php

namespace App\Actions\Driver;

use App\Models\Driver;
use App\Models\RidePool;
use App\Enums\DriverStatusEnums;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\Driver\RideAcceptedEvent;


class AcceptRideAction
{
    public function execute(Driver $driver, string $rideId): RidePool
    {
        return DB::transaction(function () use ($driver, $rideId) {

            // Lock ride to avoid double acceptance
            $ride = RidePool::lockForUpdate()->findOrFail($rideId);

            if ($ride->driver_id !== $driver->id) {
                throw new \Exception("You are not assigned to this ride.");
            }

            if ($ride->status->value !== RidePoolStatusEnums::BOOKED->value) {
                throw new \Exception("This ride is no longer available.");
            }

            $latestLocation = $driver->locations()
                ->latest('created_at')
                ->first();

            if (!$latestLocation) {
                throw new \Exception("Driver location not found.");
            }

            if (!$ride->transportMode) {
                throw new \Exception("Ride transport mode not found.");
            }
            if (empty($ride->pickup_location)) {
                throw new \Exception("Ride pickup location is missing.");
            }
            Log::info("PICKUP LOCATION RAW", [
                'pickup_location' => $ride->pickup_location
            ]);

            $maps = app(\App\Services\GoogleMapsService::class)
                ->getDistanceInKm(
                    origin: [
                        'lat' => $latestLocation->latitude,
                        'lng' => $latestLocation->longitude,
                    ],
                    destination: $ride->pickup_location,
                    mode: $ride->transportMode->type
                );


            $distanceKm = $maps['distance_km'] ?? null;
            $durationMinutes = $maps['duration_minutes'] ?? null;

            // HANDLE ZERO DISTANCE (Driver already at pickup)
            if ((float)$distanceKm === 0.0 || (int)$durationMinutes === 0) {

                Log::info("ZERO DISTANCE DETECTED: Driver already at pickup");

                $ride->update([
                    'status'              => RidePoolStatusEnums::ARRIVED->value,
                    'driver_accepted_at'  => now(),
                    'eta_minutes'         => 0,
                    'eta_timestamp'       => now(), // already here
                ]);

                // Driver becomes unavailable
                $driver->update([
                    'status' => DriverStatusEnums::UNAVAILABLE->value,
                ]);

                RideAcceptedEvent::dispatch($ride, $driver->user);

                return $ride;
            }


            $arrivalTime = now()->addMinutes($durationMinutes);


            $ride->update([
                'status'              => RidePoolStatusEnums::IN_TRANSIT->value,
                'driver_accepted_at'  => now(),
                'eta_minutes'         => $durationMinutes,
                'eta_timestamp'       => $arrivalTime,
            ]);

            // Update driver status
            $driver->update([
                'status' => DriverStatusEnums::UNAVAILABLE->value,
            ]);

            // Send event to user
            RideAcceptedEvent::dispatch($ride, $driver->user);

            return $ride;
        });
    }
}
