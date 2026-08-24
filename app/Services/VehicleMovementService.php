<?php

namespace App\Services;

use App\Models\TrackerLocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VehicleMovementService
{
    /**
     * Minimum speed considered as stopped.
     * GPS devices can report small speeds even when stationary.
     */
    protected float $stopSpeedKmh = 5.0;

    /**
     * Minimum duration before a stationary period
     * is considered a real stop.
     */
    protected int $minimumStopMinutes = 10;

    /**
     * Maximum GPS movement allowed within a stop cluster.
     */
    protected float $stopRadiusMeters = 100;

    /**
     * Return raw location history.
     */
    public function locationHistory($tracker, Carbon $from, Carbon $to): Collection
    {
        return $tracker->locations()
            ->whereBetween('tracker_time', [$from, $to])
            ->orderBy('tracker_time')
            ->get([
                'id',
                'tracker_id',
                'imei',
                'latitude',
                'longitude',
                'speed',
                'tracker_time',
            ]);
    }

    /**
     * Build movement history from GPS points.
     */
    public function movementHistory($tracker, Carbon $from, Carbon $to): array
    {
        $locations = $this->locationHistory(
            $tracker,
            $from,
            $to
        );

        if ($locations->isEmpty()) {
            return [
                'summary' => [
                    'distance_km' => 0,
                    'moving_minutes' => 0,
                    'stopped_minutes' => 0,
                    'number_of_stops' => 0,
                    'gps_points' => 0,
                ],
                'timeline' => [],
            ];
        }

        $totalDistance = $this->calculateTotalDistance($locations);

        $stops = $this->detectStops($locations);

        $timeline = $this->buildTimeline(
            $locations,
            $stops
        );

        $movingMinutes = 0;
        $stoppedMinutes = 0;
        $numberOfStops = 0;

        foreach ($timeline as $event) {

            if ($event['type'] === 'stop') {
                $numberOfStops++;

                $stoppedMinutes += $event['duration_minutes'];
            }

            if ($event['type'] === 'movement') {
                $movingMinutes += $event['duration_minutes'];
            }
        }

        return [
            'summary' => [
                'distance_km' => round($totalDistance, 2),
                'moving_minutes' => $movingMinutes,
                'stopped_minutes' => $stoppedMinutes,
                'number_of_stops' => $numberOfStops,
                'gps_points' => $locations->count(),
            ],

            'timeline' => $timeline,
        ];
    }

    /**
     * Calculate total distance travelled from GPS points.
     */

    protected function calculateTotalDistance(
        Collection $locations
    ): float {
        $locations = $locations->values();

        if ($locations->count() < 2) {
            return 0.0;
        }

        $distance = 0.0;

        for ($i = 1; $i < $locations->count(); $i++) {

            $previous = $locations->get($i - 1);
            $current = $locations->get($i);

            if (!$previous || !$current) {
                continue;
            }

            $distance += $this->distanceBetweenPoints(
                (float) $previous->latitude,
                (float) $previous->longitude,
                (float) $current->latitude,
                (float) $current->longitude
            );
        }

        return $distance;
    }

    /**
     * Detect meaningful stops.
     */
    protected function detectStops(Collection $locations): array
    {
        $stops = [];

        $stopStart = null;
        $stopPoints = [];

        foreach ($locations as $location) {

            $isStopped = ((float) $location->speed) <= $this->stopSpeedKmh;

            if (!$isStopped) {

                if ($stopStart !== null) {

                    $this->addStopIfValid(
                        $stops,
                        $stopStart,
                        $stopPoints
                    );

                    $stopStart = null;
                    $stopPoints = [];
                }

                continue;
            }

            if ($stopStart === null) {

                $stopStart = $location;
                $stopPoints = [$location];

                continue;
            }

            $firstPoint = $stopPoints[0];

            $distanceFromStart = $this->distanceBetweenPoints(
                (float) $firstPoint->latitude,
                (float) $firstPoint->longitude,
                (float) $location->latitude,
                (float) $location->longitude
            ) * 1000;

            /*
             * If the vehicle has moved too far from
             * the original stop location, end the stop.
             */
            if ($distanceFromStart > $this->stopRadiusMeters) {

                $this->addStopIfValid(
                    $stops,
                    $stopStart,
                    $stopPoints
                );

                $stopStart = $location;
                $stopPoints = [$location];

                continue;
            }

            $stopPoints[] = $location;
        }

        /*
         * Handle a stop that continues until
         * the end of the requested period.
         */
        if ($stopStart !== null) {

            $this->addStopIfValid(
                $stops,
                $stopStart,
                $stopPoints
            );
        }

        return $stops;
    }

    /**
     * Add a stop only when it lasted long enough.
     */
    protected function addStopIfValid(array &$stops, $start, array $points): void
    {
        if (empty($points)) {
            return;
        }

        $last = end($points);

        $duration = $start->tracker_time
            ->diffInMinutes($last->tracker_time);

        if ($duration < $this->minimumStopMinutes) {
            return;
        }

        $latitude = collect($points)
            ->avg(fn($point) => (float) $point->latitude);

        $longitude = collect($points)
            ->avg(fn($point) => (float) $point->longitude);

        $stops[] = [
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'arrived_at' => $start->tracker_time,
            'departed_at' => $last->tracker_time,
            'duration_minutes' => $duration,
        ];
    }

    /**
     * Build movement + stop timeline.
     */
    protected function buildTimeline(Collection $locations, array $stops): array
    {
        $timeline = [];

        if ($locations->isEmpty()) {
            return $timeline;
        }

        $movementStart = $locations->first();

        foreach ($stops as $stop) {

            $stopArrival = $stop['arrived_at'];

            /*
             * Find GPS point immediately before the stop.
             */
            $movementPoints = $locations->filter(function ($location) use ($movementStart, $stopArrival) {

                return $location->tracker_time->gte($movementStart->tracker_time)
                    && $location->tracker_time->lt($stopArrival);
            });

            if ($movementPoints->count() > 1) {

                $movementLast = $movementPoints->last();

                $movementDistance = $this->calculateTotalDistance(
                    $movementPoints
                );

                $duration = $movementStart->tracker_time
                    ->diffInMinutes($movementLast->tracker_time);

                if ($duration > 0) {
                    $timeline[] = [
                        'type' => 'movement',

                        'started_at' =>
                        $movementStart->tracker_time->toDateTimeString(),

                        'ended_at' =>
                        $movementLast->tracker_time->toDateTimeString(),

                        'duration_minutes' => $duration,

                        'distance_km' =>
                        round($movementDistance, 2),
                    ];
                }
            }

            /*
             * Add the stop.
             */
            $timeline[] = [
                'type' => 'stop',

                /*
                 * We deliberately leave "place" as null.
                 *
                 * The backend only knows latitude/longitude.
                 * Google Maps can handle the location on the frontend.
                 */
                'place' => null,

                'latitude' => $stop['latitude'],

                'longitude' => $stop['longitude'],

                'arrived_at' =>
                $stop['arrived_at']->toDateTimeString(),

                'departed_at' =>
                $stop['departed_at']->toDateTimeString(),

                'duration_minutes' =>
                $stop['duration_minutes'],
            ];

            /*
             * Next movement starts after this stop.
             */
            $movementStart = $locations
                ->first(
                    fn($location) =>
                    $location->tracker_time->gt(
                        $stop['departed_at']
                    )
                );

            if (!$movementStart) {
                break;
            }
        }

        /*
         * Handle movement after the final stop.
         */
        if ($movementStart) {

            $remainingPoints = $locations->filter(
                fn($location) =>
                $location->tracker_time->gte(
                    $movementStart->tracker_time
                )
            );

            if ($remainingPoints->count() > 1) {

                $last = $remainingPoints->last();

                $distance = $this->calculateTotalDistance(
                    $remainingPoints
                );

                $duration = $movementStart->tracker_time
                    ->diffInMinutes($last->tracker_time);

                if ($duration > 0) {
                    $timeline[] = [
                        'type' => 'movement',

                        'started_at' =>
                        $movementStart->tracker_time->toDateTimeString(),

                        'ended_at' =>
                        $last->tracker_time->toDateTimeString(),

                        'duration_minutes' => $duration,

                        'distance_km' =>
                        round($distance, 2),
                    ];
                }
            }
        }

        return $timeline;
    }

    /**
     * Haversine distance.
     *
     * Returns kilometres.
     */
    protected function distanceBetweenPoints(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371;

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a =
            sin($deltaLat / 2) ** 2 +
            cos($lat1) *
            cos($lat2) *
            sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}
