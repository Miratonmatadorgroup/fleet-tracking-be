<?php

namespace App\Actions\Delivery;

use App\Models\Delivery;
use App\Models\DriverLocation;


class AdminTrackDeliveryAction
{
    public function execute(string $trackingNumber, int $historyLimit = 20): ?Delivery
    {
        $delivery = Delivery::with([
            'customer',
            'payment',
            'driver',
            'transportMode',
        ])
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (!$delivery) {
            return null;
        }

        if ($delivery->driver_id) {
            $latestLocation = DriverLocation::where('driver_id', $delivery->driver_id)
                ->latest()
                ->first();

            $locationHistory = DriverLocation::where('driver_id', $delivery->driver_id)
                ->orderByDesc('created_at')
                ->limit($historyLimit)
                ->get()
                ->reverse()
                ->values();

            $delivery->driver_location = $latestLocation;
            $delivery->driver_location_history = $locationHistory;
        }

        return $delivery;
    }
}
