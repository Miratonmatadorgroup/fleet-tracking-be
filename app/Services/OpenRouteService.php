<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OpenRouteService
{
    public function getDistanceInKm(string $start, string $end): ?float
    {
        try {
            $startCoords = $this->geocodeAddress($start);
            $endCoords = $this->geocodeAddress($end);

            if (!$startCoords || !$endCoords) {
                Log::warning("Geocoding failed for: $start or $end");
                return null;
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://api.openrouteservice.org/v2/matrix/driving-car', [
                'headers' => [
                    'Authorization' => config('services.openrouteservice.key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'locations' => [$startCoords, $endCoords], // [[lon, lat], [lon, lat]]
                    'metrics' => ['distance'],
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            $distanceMeters = $data['distances'][0][1] ?? null;
            $distanceKm = $distanceMeters ? round($distanceMeters / 1000, 2) : null;

            Log::info("ORS distance between $start and $end: {$distanceKm} km");

            return $distanceKm;
        } catch (\Throwable $th) {
            Log::error("ORS Error: " . $th->getMessage());
            return null;
        }
    }


    private function geocodeAddress(string $location): ?array
    {
        $apiKey = config('services.openrouteservice.key');

        $response = Http::get('https://api.openrouteservice.org/geocode/search', [
            'api_key' => $apiKey,
            'text' => $location,
            'size' => 1,
        ]);

        if ($response->failed() || empty($response['features'][0]['geometry']['coordinates'])) {
            Log::warning("Geocoding failed for location: $location");
            return null;
        }

        $coordinates = $response['features'][0]['geometry']['coordinates'];
        return [$coordinates[0], $coordinates[1]]; // [lon, lat]
    }
}
