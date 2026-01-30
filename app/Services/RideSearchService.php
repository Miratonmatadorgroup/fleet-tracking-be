<?php

namespace App\Services;

use App\Models\TransportMode;
use App\Enums\DriverStatusEnums;
use App\Enums\TransportModeEnums;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Storage;
use App\Enums\TransportModeCategoryEnums;

class RideSearchService
{
    public function search(array $data): array
    {
        $pickup  = $data['pickup'];
        $dropoff = $data['dropoff'] ?? null;
        $mode    = TransportModeEnums::from(strtolower($data['transport_mode']));
        $radius  = 10; // km

        $maps = app(GoogleMapsService::class)->getCoordinatesAndDistance(
            pickupAddress: $pickup,
            dropoffAddress: $dropoff ?? $pickup,
            mode: $mode
        );

        if (!$maps) {
            throw new \Exception("Unable to geocode pickup location.");
        }

        $lat = $maps['pickup_latitude'];
        $lng = $maps['pickup_longitude'];

        // Haversine formula for distance calculation
        $distanceSql = "
            (
                6371 * ACOS(
                    COS(RADIANS(?))
                    * COS(RADIANS(dl.latitude))
                    * COS(RADIANS(dl.longitude) - RADIANS(?))
                    + SIN(RADIANS(?)) * SIN(RADIANS(dl.latitude))
                )
            )
        ";
        $bindings = [$lat, $lng, $lat];

        /*
         * Latest driver locations subquery (works on MySQL + Postgres)
         * Ensures we get only the latest location per driver
         */
        $latestLocation = DB::table('driver_locations as dl1')
            ->join(
                DB::raw('(
                    SELECT driver_id, MAX(created_at) as latest_time
                    FROM driver_locations
                    GROUP BY driver_id
                ) as dl2'),
                function ($join) {
                    $join->on('dl1.driver_id', '=', 'dl2.driver_id')
                        ->on('dl1.created_at', '=', 'dl2.latest_time');
                }
            )
            ->select('dl1.driver_id', 'dl1.latitude', 'dl1.longitude');

        // Main driver query
        $drivers = DB::table(DB::raw("({$latestLocation->toSql()}) as dl"))
            ->mergeBindings($latestLocation)
            ->join('drivers as d', 'dl.driver_id', '=', 'd.id')
            ->join('transport_modes as tm', 'tm.driver_id', '=', 'd.id')
            ->where('tm.type', strtolower($mode->value))
            ->where('tm.category', TransportModeCategoryEnums::PASSENGER->value)
            ->whereIn('d.status', [
                DriverStatusEnums::ACTIVE->value,
                DriverStatusEnums::AVAILABLE->value
            ])
            ->where('d.is_flagged', false)
            ->where('tm.is_flagged', false) 
            ->selectRaw("
                tm.id as transport_mode_id,
                tm.type,
                tm.manufacturer,
                tm.model,
                tm.color,
                tm.photo_path,
                tm.passenger_capacity,
                tm.registration_number,
                d.id as driver_id,
                d.name as driver_name,
                d.email,
                d.phone,
                d.profile_photo,
                d.gender,
                dl.latitude as driver_latitude,
                dl.longitude as driver_longitude,
                $distanceSql AS distance
            ", $bindings)
            ->whereRaw("$distanceSql <= ?", [...$bindings, $radius])
            ->orderBy('distance')
            ->limit(5)
            ->get();

        // Remove any duplicates by driver_id (in case of same timestamp)
        $drivers = $drivers->unique('driver_id')->values();

        // Format the response
        $drivers = $drivers->map(function ($item) {
            return [
                "driver" => [
                    "driver_id" => $item->driver_id,
                    "driver_name" => $item->driver_name,
                    "email" => $item->email,
                    "phone" => $item->phone,
                    "gender" => $item->gender,
                    "profile_photo" => $item->profile_photo,
                    "profile_photo_url" => $item->profile_photo
                        ? Storage::disk('public')->url($item->profile_photo)
                        : null,
                    "driver_latitude" => $item->driver_latitude,
                    "driver_longitude" => $item->driver_longitude,
                    "distance" => $item->distance,
                ],
                "transport_mode" => [
                    "transport_mode_id" => $item->transport_mode_id,
                    "type" => $item->type,
                    "manufacturer" => $item->manufacturer,
                    "model" => $item->model,
                    "color" => $item->color,
                    "registration_number" => $item->registration_number,
                    "passenger_capacity" => $item->passenger_capacity,
                    "photo_path" => $item->photo_path,
                    "photo_url" => $item->photo_path
                        ? Storage::disk('public')->url($item->photo_path)
                        : null
                ]
            ];
        });

        return [
            'search_metadata' => [
                'pickup_address'  => $pickup,
                'dropoff_address' => $dropoff,
                'mode'            => $mode->value,
                'radius_km'       => $radius,
            ],
            'maps' => $maps,
            'drivers_found' => $drivers->count(),
            'drivers' => $drivers,
        ];
    }


    public function findNearestAvailableDriver(float $lat, float $lng, int|string $modeId, float $radius = 10)
    {
        // Get the transport mode so we know category + type
        $mode = TransportMode::find($modeId);

        if (! $mode) {
            return null;
        }

        // Haversine SQL
        $distanceSql = "
        (
            6371 * ACOS(
                COS(RADIANS(?))
                * COS(RADIANS(dl.latitude))
                * COS(RADIANS(dl.longitude) - RADIANS(?))
                + SIN(RADIANS(?)) * SIN(RADIANS(dl.latitude))
            )
        )
    ";

        $bindings = [$lat, $lng, $lat];

        // Latest driver locations (works in MySQL + PostgreSQL)
        $latestLocation = DB::table('driver_locations as dl1')
            ->join(
                DB::raw('(
                SELECT driver_id, MAX(created_at) as latest_time
                FROM driver_locations
                GROUP BY driver_id
            ) as dl2'),
                function ($join) {
                    $join->on('dl1.driver_id', '=', 'dl2.driver_id')
                        ->on('dl1.created_at', '=', 'dl2.latest_time');
                }
            )
            ->select('dl1.driver_id', 'dl1.latitude', 'dl1.longitude');

        // Full driver search query
        $driver = DB::table(DB::raw("({$latestLocation->toSql()}) as dl"))
            ->mergeBindings($latestLocation)
            ->join('drivers as d', 'dl.driver_id', '=', 'd.id')
            ->join('transport_modes as tm', 'tm.driver_id', '=', 'd.id')
            ->where('tm.id', $modeId)
            ->whereIn('d.status', [
                DriverStatusEnums::ACTIVE->value,
                DriverStatusEnums::AVAILABLE->value
            ])
            ->selectRaw("d.id as driver_id, tm.partner_id, dl.latitude, dl.longitude, $distanceSql as distance", $bindings)
            ->whereRaw("$distanceSql <= ?", [...$bindings, $radius])
            ->orderBy('distance')
            ->first();

        if (! $driver) {
            return null;
        }

        // Return the driver with transport mode relation
        return \App\Models\Driver::with('transportMode')->find($driver->driver_id);
    }
}
