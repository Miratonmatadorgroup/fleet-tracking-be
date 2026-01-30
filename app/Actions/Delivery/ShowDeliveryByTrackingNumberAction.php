<?php

namespace App\Actions\Delivery;

use App\Models\Delivery;
use App\Models\DriverLocation;

class ShowDeliveryByTrackingNumberAction
{
    // public function execute(string $trackingNumber, ?string $customerId = null, int $historyLimit = 20): ?Delivery
    // {
    //     $delivery = Delivery::with(['driver', 'transportMode']) // eager load
    //         ->where('tracking_number', $trackingNumber)
    //         ->where('customer_id', $customerId)
    //         ->first();

    //     if (!$delivery) {
    //         return null;
    //     }

    //     if ($delivery->driver_id) {
    //         $latestLocation = DriverLocation::where('driver_id', $delivery->driver_id)
    //             ->latest()
    //             ->first();

    //         $locationHistory = DriverLocation::where('driver_id', $delivery->driver_id)
    //             ->orderByDesc('created_at')
    //             ->limit($historyLimit)
    //             ->get()
    //             ->reverse()
    //             ->values();

    //         // attach dynamically
    //         $delivery->driver_location = $latestLocation;
    //         $delivery->driver_location_history = $locationHistory;
    //     }

    //     return $delivery;
    // }


    public function execute(string $trackingNumber, ?string $customerId = null, int $historyLimit = 20): ?Delivery
    {
        $query = Delivery::with(['driver', 'transportMode'])
            ->where('tracking_number', $trackingNumber);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $delivery = $query->first();

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
