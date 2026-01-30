<?php
namespace App\Actions\Driver;

use App\Models\DriverLocation;
use App\Enums\TransportModeEnums;
use App\Services\GoogleMapsService;
use App\DTOs\Driver\ShowDirectionsDTO;

class ShowDirectionsAction
{
    public function __construct(
        protected GoogleMapsService $googleMaps
    ) {}

    public function execute(ShowDirectionsDTO $dto): array
    {
        $driver = $dto->user->driver;


        $mode = $driver->transport_mode ?? TransportModeEnums::CAR;


        $driverLocation = DriverLocation::where('driver_id', $driver->id)
            ->latest('created_at')
            ->first();

        $origin = $driverLocation
            ? ['lat' => $driverLocation->latitude, 'lng' => $driverLocation->longitude]
            : $driver->address;

        $destination = $dto->pickupLat && $dto->pickupLng
            ? ['lat' => $dto->pickupLat, 'lng' => $dto->pickupLng]
            : $dto->pickupLocation;

        $result = $this->googleMaps->getDistanceInKm($origin, $destination, $mode);

        return [
            'driver_location' => $origin,
            'pickup_address'  => $dto->pickupLocation,
            'pickup_latitude' => $dto->pickupLat,
            'pickup_longitude'=> $dto->pickupLng,
            'distance_km'     => $result['distance_km'] ?? null,
            'duration_minutes'=> $result['duration_minutes'] ?? null,
            'eta'             => isset($result['duration_minutes'])
                ? now()->addMinutes($result['duration_minutes'])
                : null,
        ];
    }
}
