<?php

namespace App\Services;


use App\Enums\GeoFenceActionTypeEnums;
use App\Models\Asset;
use App\Models\Geofence;
use App\Models\GeofenceBreach;
use App\Models\GeofenceState;
use App\Notifications\User\GeofenceAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeofenceService
{
    public function __construct(
        protected TrackerService $trackerService
    ) {}

    // Process all geofences attached to an asset. This method should be called every time a new GPS location is received.
    public function process(Asset $asset): void
    {
        try {

            if (!$asset->hasActiveSubscription()) {
                return;
            }

            if (blank($asset->imei)) {
                return;
            }

            $location = $asset->getLastKnownLocation();

            if (!$location) {
                return;
            }

            $geofences = $asset->geofences()->where('is_active', true)->get();

            if ($geofences->isEmpty()) {
                return;
            }

            foreach ($geofences as $geofence) {

                $this->processGeofence($asset, $geofence, $location);
            }
        } catch (\Throwable $exception) {

            Log::error('Geofence processing failed.', [

                'asset_id' => $asset->id,

                'message' => $exception->getMessage(),

                'trace' => $exception->getTraceAsString(),

            ]);
        }
    }

    // Process one geofence.
    /**
     * @param array{
     *     latitude: float,
     *     longitude: float,
     *     timestamp: ?string
     * } $location
     */
    private function processGeofence(Asset $asset, Geofence $geofence, array $location): void
    {

        // Calculate current distance.
        $distance = $this->calculateDistance($location['latitude'], $location['longitude'], $geofence);

        //Determine whether vehicle is inside the fence.
        $isInside = $distance <= $geofence->radius_meters;

        // Get previous state.
        $state = $this->getCurrentState($asset, $geofence);

        // Detect transitions.
        if (!$state->is_inside && $isInside) {

            $this->handleEntry($asset, $geofence, $state);
        } elseif ($state->is_inside && !$isInside) {

            $this->handleExit($asset, $geofence, $state);
        }
    }

    // Return the current geofence state.
    private function getCurrentState(Asset $asset, Geofence $geofence): GeofenceState
    {

        return GeofenceState::firstOrCreate(

            [

                'asset_id' => $asset->id,

                'geofence_id' => $geofence->id,

            ],

            [

                'is_inside' => false,

            ]

        );
    }

    // Calculate the distance between asset and geofence. Returns distance in meters.
    private function calculateDistance(float $latitude, float $longitude, Geofence $geofence): float {

    $coordinates = $geofence->coordinates;

    /**
     * Support both coordinate formats.
     *
     * OLD FORMAT
     * [
     *     [
     *         'lat' => 6.415,
     *         'lng' => 2.885,
     *     ]
     * ]
     *
     * NEW FORMAT
     * [
     *     'latitude' => 6.415,
     *     'longitude' => 2.885,
     * ]
     */
    if (
        isset($coordinates['latitude']) &&
        isset($coordinates['longitude'])
    ) {

        $fenceLatitude = (float) $coordinates['latitude'];
        $fenceLongitude = (float) $coordinates['longitude'];

    } elseif (
        isset($coordinates[0]['lat']) &&
        isset($coordinates[0]['lng'])
    ) {

        $fenceLatitude = (float) $coordinates[0]['lat'];
        $fenceLongitude = (float) $coordinates[0]['lng'];

    } else {

        Log::warning('Invalid geofence coordinates.', [
            'geofence_id' => $geofence->id,
            'coordinates' => $coordinates,
        ]);

        return PHP_FLOAT_MAX;
    }

    $earthRadius = 6371000; // meters

    $latFrom = deg2rad($latitude);
    $lonFrom = deg2rad($longitude);

    $latTo = deg2rad($fenceLatitude);
    $lonTo = deg2rad($fenceLongitude);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(
        sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) *
            cos($latTo) *
            pow(sin($lonDelta / 2), 2)
        )
    );

    return $earthRadius * $angle;
}

    // Vehicle has entered a geofence.
    private function handleEntry(Asset $asset, Geofence $geofence, GeofenceState $state): void
    {

        DB::transaction(function () use (
            $asset,
            $geofence,
            $state
        ) {

            $state->update([
                'is_inside' => true,
            ]);

            $this->createBreach(
                asset: $asset,
                geofence: $geofence,
                breachType: 'entry'
            );

            Log::info('Vehicle entered geofence.', [
                'asset_id' => $asset->id,
                'geofence_id' => $geofence->id,
            ]);
        });

        // Execute AFTER transaction commits
        $this->performAction(
            $asset,
            $geofence
        );
    }

    //Vehicle has exited a geofence.
    private function handleExit(Asset $asset, Geofence $geofence, GeofenceState $state): void
    {

        DB::transaction(function () use ($asset, $geofence, $state) {
            // Update state.
            $state->update([

                'is_inside' => false,

            ]);
            // Save breach.
            $this->createBreach(asset: $asset, geofence: $geofence, breachType: 'exit');
            Log::info('Vehicle exited geofence.', [

                'asset_id' => $asset->id,

                'geofence_id' => $geofence->id,

            ]);
        });
    }

    // Save the geofence breach.
    private function createBreach(Asset $asset, Geofence $geofence, string $breachType): void
    {
        GeofenceBreach::create([

            'asset_id' => $asset->id,

            'geofence_id' => $geofence->id,

            'breach_type' => $breachType,

            'latitude' => $asset->last_known_lat,

            'longitude' => $asset->last_known_lng,

            'timestamp' => now(),

        ]);

        Log::info('Geofence breach recorded.', [

            'asset_id' => $asset->id,

            'geofence_id' => $geofence->id,

            'type' => $breachType,

        ]);
    }

    // Execute configured action for the geofence.
    private function performAction(Asset $asset, Geofence $geofence): void
    {

        switch ($geofence->action) {

            case GeoFenceActionTypeEnums::NONE:

                Log::info('No action configured for geofence.', [
                    'asset_id' => $asset->id,
                    'geofence_id' => $geofence->id,
                ]);

                break;

            case GeoFenceActionTypeEnums::ALERT:

                $this->sendAlert(
                    $asset,
                    $geofence
                );

                break;

            case GeoFenceActionTypeEnums::SHUTDOWN:

                $this->shutdownVehicle(
                    $asset
                );

                break;

            default:

                Log::warning('Unknown geofence action.', [
                    'asset_id' => $asset->id,
                    'geofence_id' => $geofence->id,
                    'action' => $geofence->action,
                ]);

                break;
        }
    }

    // Shutdown vehicle Using Tracker.
    private function shutdownVehicle(Asset $asset): void
    {
        try {

            if (blank($asset->imei)) {

                Log::warning('Unable to shutdown vehicle. IMEI missing.', [

                    'asset_id' => $asset->id,

                ]);

                return;
            }

            $response = $this->trackerService->lockVehicle(
                $asset->imei
            );

            if (($response['status'] ?? -1) === 0) {

                Log::info('Vehicle shutdown command accepted.', [

                    'asset_id' => $asset->id,

                    'imei' => $asset->imei,

                    'message' => $response['message'] ?? null,

                    'tracker_response' => $response,

                ]);
            } else {

                Log::warning('Vehicle shutdown command rejected.', [

                    'asset_id' => $asset->id,

                    'imei' => $asset->imei,

                    'message' => $response['message'] ?? null,

                    'tracker_response' => $response,

                ]);
            }
        } catch (\Throwable $exception) {

            Log::error('Vehicle shutdown failed.', [

                'asset_id' => $asset->id,

                'imei' => $asset->imei,

                'message' => $exception->getMessage(),

            ]);
        }
    }

    // Send notification.
    private function sendAlert(Asset $asset, Geofence $geofence): void
    {

        try {

            $asset->loadMissing('organization.user');

            $user = $geofence->user
                ?? $asset->organization?->user;

            if (!$user) {

                Log::warning('Unable to send geofence alert. No recipient found.', [

                    'asset_id' => $asset->id,

                    'geofence_id' => $geofence->id,

                ]);

                return;
            }

            $user->notify(new GeofenceAlertNotification($asset, $geofence, $geofence->action));

            Log::info('Geofence notification sent.', [

                'asset_id' => $asset->id,

                'geofence_id' => $geofence->id,

                'user_id' => $user->id,

            ]);
        } catch (\Throwable $exception) {

            Log::error('Failed to send geofence notification.', [

                'asset_id' => $asset->id,

                'geofence_id' => $geofence->id,

                'message' => $exception->getMessage(),

            ]);
        }
    }
}
