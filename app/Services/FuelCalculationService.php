<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\GpsLog;

class FuelCalculationService
{
    public function calculate(Asset $asset, string $date): ?array
    {
        $gpsLogs = GpsLog::where('asset_id', $asset->id)
            ->whereDate('timestamp', $date)
            ->orderBy('timestamp')
            ->get();

        if ($gpsLogs->count() < 2) {
            return null; // Not enough data
        }

        // 1. Calculate Distance (D)
        $totalDistance = 0;
        for ($i = 1; $i < $gpsLogs->count(); $i++) {
            $prev = $gpsLogs[$i - 1];
            $curr = $gpsLogs[$i];

            $totalDistance += $this->haversineDistance(
                $prev->latitude,
                $prev->longitude,
                $curr->latitude,
                $curr->longitude
            );
        }

        // 2. Calculate Idle Time (T_idle)
        $idleHours = 0;
        foreach ($gpsLogs as $log) {
            if ($log->speed == 0 && $log->ignition == true) {
                // Assume each log represents 1 minute (adjust based on hardware frequency)
                $idleHours += 1 / 60; // Convert minutes to hours
            }
        }

        // 3. Calculate Speeding Distance (D_speeding)
        $speedingDistance = 0;
        for ($i = 1; $i < $gpsLogs->count(); $i++) {
            $prev = $gpsLogs[$i - 1];
            $curr = $gpsLogs[$i];

            if ($curr->speed > 100) {
                $speedingDistance += $this->haversineDistance(
                    $prev->latitude,
                    $prev->longitude,
                    $curr->latitude,
                    $curr->longitude
                );
            }
        }

        // 4. Apply Master Formula
        $baseFuel = $totalDistance * ($asset->base_consumption_rate ?? 0.25);
        $idleFuel = $idleHours * ($asset->idle_consumption_rate ?? 2.5);
        $speedingFuel = $speedingDistance * ($asset->base_consumption_rate ?? 0.25) * ($asset->speeding_penalty ?? 0.15);

        $totalFuel = $baseFuel + $idleFuel + $speedingFuel;

        return [
            'distance_km' => round($totalDistance, 2),
            'idle_hours' => round($idleHours, 2),
            'speeding_km' => round($speedingDistance, 2),
            'base_fuel' => round($baseFuel, 2),
            'idle_fuel' => round($idleFuel, 2),
            'speeding_fuel' => round($speedingFuel, 2),
            'total_fuel' => round($totalFuel, 2),
            'avg_speed' => round($gpsLogs->avg('speed'), 2),
            'max_speed' => round($gpsLogs->max('speed'), 2),
        ];
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}