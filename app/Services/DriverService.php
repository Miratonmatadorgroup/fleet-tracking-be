<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Delivery;
use App\Models\TransportMode;
use App\Enums\DriverStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Enums\DriverApplicationStatusEnums;

class DriverService
{
    /**
     * Find and assign the nearest available driver atomically.
     */

    public function findNearestAvailable(Delivery $delivery, float $radiusInKm = 10): ?Driver
    {
        return DB::transaction(function () use ($delivery, $radiusInKm) {

            $lat = $delivery->pickup_latitude;
            $lng = $delivery->pickup_longitude;

            // Haversine formula with parameter bindings
            $driverRow = DB::table('driver_locations as dl')
                ->join('drivers as d', 'dl.driver_id', '=', 'd.id')
                ->join('transport_modes as tm', 'tm.driver_id', '=', 'd.id')
                ->where('tm.type', $delivery->mode_of_transportation)
                ->where('d.application_status', DriverApplicationStatusEnums::APPROVED->value)
                ->whereIn('d.status', [
                    DriverStatusEnums::ACTIVE->value,
                    DriverStatusEnums::AVAILABLE->value,
                ])
                ->select(
                    'd.id',
                    'tm.id as transport_mode_id',
                    DB::raw("
            (6371 * acos(
                cos(radians(?))
                * cos(radians(dl.latitude))
                * cos(radians(dl.longitude) - radians(?))
                + sin(radians(?)) * sin(radians(dl.latitude))
            )) as distance
        ")
                )
                ->addBinding([$lat, $lng, $lat], 'select')
                ->whereRaw("
        (6371 * acos(
            cos(radians(?))
            * cos(radians(dl.latitude))
            * cos(radians(dl.longitude) - radians(?))
            + sin(radians(?)) * sin(radians(dl.latitude))
        )) <= ?
    ", [$lat, $lng, $lat, $radiusInKm])
                ->orderByRaw("
        (6371 * acos(
            cos(radians(?))
            * cos(radians(dl.latitude))
            * cos(radians(dl.longitude) - radians(?))
            + sin(radians(?)) * sin(radians(dl.latitude))
        ))
    ", [$lat, $lng, $lat])
                ->lockForUpdate()
                ->first();


            if (!$driverRow) {
                // No driver found within radius
                return null;
            }

            $driver = Driver::find($driverRow->id);

            if ($driver) {
                // Assign + mark unavailable atomically
                $driver->status = DriverStatusEnums::UNAVAILABLE;
                $driver->save();

                $delivery->driver_id = $driver->id;
                $delivery->transport_mode_id = $driverRow->transport_mode_id;
                $delivery->save();
            }

            return $driver;
        });
    }

    public function getBestAvailableModeForDelivery(float $pickupLat, float $pickupLng, float $dropoffLat, float $dropoffLng): ?string
    {
        // Query for the closest driver regardless of mode
        $driverRow = DB::table('driver_locations as dl')
            ->join('drivers as d', 'dl.driver_id', '=', 'd.id')
            ->join('transport_modes as tm', 'tm.driver_id', '=', 'd.id')
            ->where('d.application_status', DriverApplicationStatusEnums::APPROVED->value)
            ->whereIn('d.status', [
                DriverStatusEnums::ACTIVE->value,
                DriverStatusEnums::AVAILABLE->value,
            ])
            ->select(
                'tm.type',
                DB::raw("(
                6371 * acos(
                    cos(radians(?))
                    * cos(radians(dl.latitude))
                    * cos(radians(dl.longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(dl.latitude))
                )
            ) as distance")
            )
            ->addBinding([$pickupLat, $pickupLng, $pickupLat], 'select')
            ->orderBy('distance')
            ->first();

        return $driverRow?->type;
    }

    public function checkDriverAndModeStatus(TransportMode $modeModel): Driver
    {
        if ($modeModel->is_flagged) {
            throw new \Exception(
                "The selected transport mode ({$modeModel->type->value}) is temporarily unavailable. Reason: {$modeModel->flag_reason}",
                422
            );
        }

        $driver = $modeModel->driver;

        if (!$driver) {
            throw new \Exception("No driver assigned to this transport mode.", 422);
        }

        if ($driver->is_flagged) {
            throw new \Exception(
                "The assigned driver is currently unavailable. Reason: {$driver->flag_reason}",
                422
            );
        }

        return $driver;
    }
}
