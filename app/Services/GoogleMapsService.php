<?php

namespace App\Services;

use App\Enums\TransportModeEnums;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.key');
    }

    /**
     * Map our transport enums to Google travelMode values.
     */
    protected function mapTransportModeToGoogle(TransportModeEnums $mode): string
    {
        return match ($mode) {
            TransportModeEnums::BIKE     => 'TWO_WHEELER', // motorbike (limited support)
            TransportModeEnums::CAR,
            TransportModeEnums::VAN,
            TransportModeEnums::TRUCK    => 'DRIVE',
            TransportModeEnums::BUS,
            TransportModeEnums::SHIP,
            TransportModeEnums::BOAT,
            TransportModeEnums::AIR      => 'TRANSIT',
            default                      => 'DRIVE',
        };
    }

    /**
     * Get distance and duration between two points.
     */
    public function getDistanceInKm(array|string $origin, array|string $destination, TransportModeEnums $mode): ?array
    {
        try {
            if (is_string($origin)) {
                $origin = $this->geocodeAddress($origin);
            }
            if (is_string($destination)) {
                $destination = $this->geocodeAddress($destination);
            }

            $googleMode = $this->mapTransportModeToGoogle($mode);
            $response = $this->callGoogleRoutesApi($origin, $destination, $googleMode);

            if (!$response['success'] && $googleMode !== 'DRIVE') {
                Log::warning("Retrying with DRIVE as fallback", compact('origin', 'destination', 'googleMode'));
                $response = $this->callGoogleRoutesApi($origin, $destination, 'DRIVE');
                $response['fallback_used'] = true;
            }
            if (!$response['success']) {
                Log::error("GET DISTANCE FAILED", [
                    'origin' => $origin,
                    'destination' => $destination,
                    'mode' => $mode
                ]);
                return null;
            }
            return $response['success'] ? $response['data'] : null;
        } catch (\Throwable $e) {
            Log::error('Google Routes API error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calls Google Routes API.
     */
    private function callGoogleRoutesApi(array $origin, array $destination, string $travelMode): array
    {
        $url = 'https://routes.googleapis.com/directions/v2:computeRoutes';

        Log::info("GOOGLE ROUTES REQUEST", [
            'origin'      => $origin,
            'destination' => $destination,
            'mode'        => $travelMode,
        ]);

        $res = Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Goog-Api-Key'   => $this->apiKey,
            'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters',
        ])->post($url, [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude'  => $origin['lat'],
                        'longitude' => $origin['lng'],
                    ]
                ]
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude'  => $destination['lat'],
                        'longitude' => $destination['lng'],
                    ]
                ]
            ],
            'travelMode'       => $travelMode,
            'routingPreference' => 'TRAFFIC_AWARE',
        ]);

        if ($res->failed()) {
            Log::error("Google Routes API request failed", ['response' => $res->body()]);
            return ['success' => false];
        }
        Log::error("GOOGLE ROUTES API FAILED", [
            'origin'      => $origin,
            'destination' => $destination,
            'mode'        => $travelMode,
            'response'    => $res->body()
        ]);


        $data = $res->json();

        if (empty($data['routes'][0])) {
            Log::warning("Google Routes API returned no route", compact('origin', 'destination', 'travelMode'));
            Log::error("GOOGLE ROUTES NO ROUTE FOUND", [
                'origin'      => $origin,
                'destination' => $destination,
                'mode'        => $travelMode,
                'response'    => $data
            ]);
            return ['success' => false];
        }

        $route = $data['routes'][0];
        $distanceMeters = $route['distanceMeters'] ?? null;
        $durationSeconds = isset($route['duration'])
            ? (int) filter_var($route['duration'], FILTER_SANITIZE_NUMBER_INT)
            : null;

        return [
            'success' => true,
            'data' => [
                'distance_km'      => $distanceMeters ? $distanceMeters / 1000 : null,
                'duration_minutes' => $durationSeconds ? round($durationSeconds / 60) : null,
            ]
        ];
    }

    /**
     * Geocode an address into lat/lng.
     */
    // public function geocodeAddress(string $address): array
    // {
    //     $endpoint = 'https://maps.googleapis.com/maps/api/geocode/json';

    //     $response = Http::get($endpoint, [
    //         'address' => $address,
    //         'key'     => $this->apiKey,
    //     ]);

    //     if ($response->failed()) {
    //         throw new Exception('Failed to connect to Google Geocoding API.');
    //     }

    //     $data = $response->json();

    //     if ($data['status'] !== 'OK' || empty($data['results'])) {
    //         throw new Exception('Google Geocoding API error: ' . $data['status']);
    //     }

    //     $location = $data['results'][0]['geometry']['location'];

    //     return [
    //         'lat' => $location['lat'],
    //         'lng' => $location['lng'],
    //     ];
    // }

    public function geocodeAddress(string $address): array
    {
        // CLEAN ADDRESS
        $address = trim($address);
        $address = rtrim($address, '.');
        $address = str_replace(['No.', 'NO.', 'no.'], '', $address);
        $address = str_replace('  ', ' ', $address);

        $endpoint = 'https://maps.googleapis.com/maps/api/geocode/json';

        $response = Http::get($endpoint, [
            'address' => $address,
            'key'     => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to connect to Google Geocoding API.');
        }

        $data = $response->json();

        if ($data['status'] !== 'OK' || empty($data['results'])) {
            Log::error('GEOCODE FAILED', ['address' => $address, 'status' => $data['status']]);
            throw new Exception('Google Geocoding API error: ' . $data['status']);
        }

        $location = $data['results'][0]['geometry']['location'];

        return [
            'lat' => $location['lat'],
            'lng' => $location['lng'],
        ];
    }


    public function getCoordinatesAndDistance(
        string $pickupAddress,
        string $dropoffAddress,
        TransportModeEnums $mode
    ): array {
        //Geocode addresses → lat/lng
        $pickupCoords = $this->geocodeAddress($pickupAddress);
        $dropoffCoords = $this->geocodeAddress($dropoffAddress);

        //Get distance + duration
        $distanceData = $this->getDistanceInKm($pickupCoords, $dropoffCoords, $mode);

        $result = [
            'pickup_latitude'   => $pickupCoords['lat'],
            'pickup_longitude'  => $pickupCoords['lng'],
            'dropoff_latitude'  => $dropoffCoords['lat'],
            'dropoff_longitude' => $dropoffCoords['lng'],
            'distance_km'       => $distanceData['distance_km'] ?? null,
            'duration_minutes'  => $distanceData['duration_minutes'] ?? null,
            'eta'               => isset($distanceData['duration_minutes'])
                ? now()->addMinutes($distanceData['duration_minutes'])
                : null,
        ];

        Log::info('Google Maps Coordinates + Distance', [
            'pickup_address'   => $pickupAddress,
            'dropoff_address'  => $dropoffAddress,
            'pickup_coords'    => $pickupCoords,
            'dropoff_coords'   => $dropoffCoords,
            'mode'             => $mode->value,
            'distance_km'      => $result['distance_km'],
            'duration_minutes' => $result['duration_minutes'],
            'eta'              => $result['eta']?->toDateTimeString(),
        ]);

        return $result;
    }

    /**
     * Reverse geocode coordinates to get address components (city/state).
     */

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $endpoint = 'https://maps.googleapis.com/maps/api/geocode/json';

        $response = Http::get($endpoint, [
            'latlng' => "{$lat},{$lng}",
            'key'    => $this->apiKey,
        ]);

        if ($response->failed()) {
            Log::error('Google Reverse Geocoding API failed', ['lat' => $lat, 'lng' => $lng]);
            return null;
        }

        $data = $response->json();

        if ($data['status'] !== 'OK' || empty($data['results'])) {
            Log::warning('Google Reverse Geocoding API issue', [
                'status' => $data['status'],
                'error'  => $data['error_message'] ?? null,
                'lat'    => $lat,
                'lng'    => $lng,
            ]);
            return null;
        }

        $city     = null;
        $state    = null;
        $district = null;
        $country  = null;

        // Loop through results until city/state/country found
        foreach ($data['results'] as $result) {
            $components = $result['address_components'] ?? [];

            foreach ($components as $component) {
                if (in_array('locality', $component['types']) || in_array('sublocality', $component['types'])) {
                    $city = $city ?? $component['long_name'];
                }
                if (in_array('administrative_area_level_1', $component['types'])) {
                    $state = $state ?? $component['long_name'];
                }
                if (in_array('administrative_area_level_2', $component['types'])) {
                    $district = $district ?? $component['long_name'];
                }
                if (in_array('country', $component['types'])) {
                    $country = $country ?? $component['long_name'];
                }
            }

            // stop early if we already found everything
            if ($city && $state && $district && $country) {
                break;
            }
        }

        // Fallback if no city/state/country
        $fallbackAddress = $data['results'][0]['formatted_address'] ?? null;

        if (!$city) {
            $city = $fallbackAddress;
        }
        if (!$state) {
            $state = $fallbackAddress;
        }
        if (!$country) {
            $country = $fallbackAddress;
        }

        return [
            'city'     => $city,
            'state'    => $state,
            'district' => $district,
            'country'  => $country,
        ];
    }
}
