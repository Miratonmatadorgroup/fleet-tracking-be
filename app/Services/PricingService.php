<?php

namespace App\Services;

use Carbon\Carbon;
use App\Enums\DeliveryTypeEnums;
use App\Enums\TransportModeEnums;
use App\Models\RidePoolingPricing;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Log;

class PricingService
{
    public function __construct(
        protected GoogleMapsService $googleMaps
    ) {}

    /**
     * Calculate delivery price and ETA
     */
    public function calculatePriceAndETA(
        array|string $pickup,
        array|string $dropoff,
        TransportModeEnums $mode = TransportModeEnums::CAR,
        ?string $deliveryType = null
    ): array {
        // Geocode if needed
        if (is_string($pickup)) {
            $pickup = $this->googleMaps->geocodeAddress($pickup);
        }
        if (is_string($dropoff)) {
            $dropoff = $this->googleMaps->geocodeAddress($dropoff);
        }

        if (!is_array($pickup) || !isset($pickup['lat'], $pickup['lng'])) {
            throw new \InvalidArgumentException('Pickup location is invalid.');
        }
        if (!is_array($dropoff) || !isset($dropoff['lat'], $dropoff['lng'])) {
            throw new \InvalidArgumentException('Dropoff location is invalid.');
        }

        // Fetch route info
        $routeData = $this->googleMaps->getDistanceInKm($pickup, $dropoff, $mode);

        if (!$routeData || empty($routeData['distance_km'])) {
            throw new \Exception("No route found between given coordinates.");
        }

        $distanceKm      = $routeData['distance_km'];
        $durationMinutes = $routeData['duration_minutes'];

        //Log the distance + mode for debugging
        Log::info('Google Maps Distance Calculation', [
            'pickup'            => $pickup,
            'dropoff'           => $dropoff,
            'mode'              => $mode->value,
            'distance_km'       => $distanceKm,
            'duration_minutes'  => $durationMinutes,
        ]);

        $baseRate = $this->getRateForLocations($pickup, $dropoff, $mode);

        Log::info("Base rate for {$mode->value} from pickup to dropoff: ₦{$baseRate}");

        $subtotal = $distanceKm * $baseRate;

        $deliveryTypeFactor = match ($deliveryType) {
            DeliveryTypeEnums::QUICK->value     => 1.5,
            DeliveryTypeEnums::NEXT_DAY->value  => 1.2,
            default                             => 1,
        };

        $subtotal *= $deliveryTypeFactor;

        $isInternational = $this->isInternational($pickup, $dropoff);

        if ($isInternational) {
            $importCharges = 0.35; // 35% for international
            $tax = round($subtotal * $importCharges, 2);
        } else {
            $vatRate = 0.075; // 7.5% VAT
            $tax = round($subtotal * $vatRate, 2);
        }

        $total = round($subtotal + $tax, 2);

        $estimatedDays = $this->getEstimatedDays($deliveryType, $isInternational, $mode);

        $eta = $this->formatDuration($durationMinutes * 60);

        return [
            'distance_km'      => round($distanceKm, 2),
            'duration_minutes' => $durationMinutes,
            'estimated_days'   => $estimatedDays,
            'eta'              => $eta,
            'subtotal'         => round($subtotal, 2),
            'tax'              => $tax,
            'total'            => $total,
        ];
    }


    private function getRateForLocations(array $pickup, array $dropoff, TransportModeEnums $mode): float
    {
        $rate = DB::table('transport_mode_pricings')
            ->where('mode', $mode->value)
            ->value('price_per_km');

        if (!$rate) {
            throw new \Exception("No pricing rate found for mode: {$mode->value}");
        }

        return (float) $rate;
    }

    private function isInternational(array $pickup, array $dropoff): bool
    {
        return ($pickup['country'] ?? null) !== ($dropoff['country'] ?? null);
    }

    private function getEstimatedDays(?string $deliveryType, bool $isInternational, TransportModeEnums $mode): int
    {
        return match ($deliveryType) {
            DeliveryTypeEnums::QUICK->value     => $isInternational ? 3 : 1,
            DeliveryTypeEnums::NEXT_DAY->value  => $isInternational ? 5 : 2,
            default                             => $isInternational ? 7 : 3,
        };
    }

    private function formatDuration(int $seconds): string
    {
        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $hours > 0
            ? "{$hours}h {$minutes}m"
            : "{$minutes}m";
    }



    /**
     * Calculate smart ride pooling price for all scenarios
     */
    public function calculateRidePoolingPrice(
        array|string $pickup,
        array|string|null $dropoff = null,
        string $transportMode,               // e.g. "van", "car"
        ?string $ridePoolingCategory = null, // e.g. "luxury_van"
        ?float $usageHours = null            // e.g. 5, 6, 24
    ): array {
        $distanceKm = 0;
        $tripDurationMinutes = 0;

        if (is_string($pickup)) {
            $pickup = ['address' => $pickup];
        }

        if ($dropoff !== null && $dropoff !== '') {
            if (is_string($dropoff)) {
                $dropoff = ['address' => $dropoff];
            }
        } else {
            // normalize empty string to null
            $dropoff = null;
        }

        // Use the helper to ensure lat/lng are present (geocode if necessary)
        $pickup = $this->normalizeLocation($pickup);

        if ($dropoff) {
            $dropoff = $this->normalizeLocation($dropoff);
        }

        //If we have both pickup & dropoff → get distance & time from Google Maps
        if ($dropoff && isset($pickup['lat'], $pickup['lng'], $dropoff['lat'], $dropoff['lng'])) {
            $transportModeEnum = TransportModeEnums::from(strtolower($transportMode));
            $routeData = $this->googleMaps->getDistanceInKm($pickup, $dropoff, $transportModeEnum);
            $distanceKm = (float) ($routeData['distance_km'] ?? 0);
            $tripDurationMinutes = (float) ($routeData['duration_minutes'] ?? 0);
        }

        Log::info('Ride Pooling Route Data', [
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'distance_km' => $distanceKm,
            'trip_duration_minutes' => $tripDurationMinutes,
            'usage_hours' => $usageHours,
        ]);

        //Get base & optional fares
        $baseFare = (float) DB::table('transport_mode_pricings')
            ->where('mode', strtolower(trim($transportMode)))
            ->value('price_per_km');

        if (!$baseFare) {
            throw new \Exception("No base fare found for transport mode: {$transportMode}");
        }

        $addedFare = 0;
        if ($ridePoolingCategory) {
            $addedFare = (float) (RidePoolingPricing::where('category', strtolower(trim($ridePoolingCategory)))
                ->value('base_price') ?? 0);
        }

        $effectiveRatePerKm = $baseFare + $addedFare;
        $pricePerMinute = round($effectiveRatePerKm / 2, 2);
        $hourlyRate = round($effectiveRatePerKm * 10, 2);

        //Pricing logic based on scenario
        $distanceCost = 0;
        $timeCost = 0;
        $usageCost = 0;

        if ($dropoff && !$usageHours) {
            // Case 1: Normal trip → use Google data
            $distanceCost = $distanceKm * $effectiveRatePerKm;
            $timeCost = $tripDurationMinutes * $pricePerMinute;
        } elseif ($dropoff && $usageHours) {
            // Case 2: Timed trip → use distance + booked time (ignore Google time)
            $distanceCost = $distanceKm * $effectiveRatePerKm;
            $usageCost = $usageHours * $hourlyRate;
        } elseif (!$dropoff && $usageHours) {
            // Case 3: Full-day or hourly hire → ignore distance/time, charge per hour
            $usageCost = $usageHours * $hourlyRate;
        }

        //Apply dynamic multipliers
        $isPeak = $this->isPeakHour();
        $isNight = $this->isNightHour();
        $isWeekend = $this->isWeekend();
        $isOffPeakDiscount = $this->isOffPeakDiscount();
        $isHoliday = $this->isHoliday();

        $peakFactor = $isPeak ? 1.3 : 1.0;
        $nightFactor = $isNight ? 1.2 : 1.0;
        $weekendFactor = $isWeekend ? 1.1 : 1.0;
        $offPeakFactor = $isOffPeakDiscount ? 0.9 : 1.0;
        $holidayFactor = $isHoliday ? $this->getHolidayFactor() : 1.0;

        $combinedFactor = $peakFactor * $nightFactor * $weekendFactor * $offPeakFactor * $holidayFactor;

        $subtotal = ($distanceCost + $timeCost + $usageCost) * $combinedFactor;

        //Add VAT (7.5%)
        $taxRate = 0.075;
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'transport_mode'         => $transportMode,
            'ride_pool_category'     => $ridePoolingCategory,
            'distance_km'            => round($distanceKm, 2),
            'trip_duration_min'      => round($tripDurationMinutes, 2),
            'usage_hours'            => $usageHours,
            'base_fare'              => $baseFare,
            'added_fare'             => $addedFare,
            'effective_rate_per_km'  => $effectiveRatePerKm,
            'price_per_minute'       => $pricePerMinute,
            'hourly_rate'            => $hourlyRate,
            'distance_cost'          => round($distanceCost, 2),
            'time_cost'              => round($timeCost, 2),
            'usage_cost'             => round($usageCost, 2),
            'is_peak_hour'           => $isPeak,
            'is_night_hour'          => $isNight,
            'is_weekend'             => $isWeekend,
            'is_holiday'             => $isHoliday,
            'holiday_factor'         => $holidayFactor,
            'is_off_peak_discount'   => $isOffPeakDiscount,
            'combined_factor'        => $combinedFactor,
            'subtotal'               => round($subtotal, 2),
            'tax'                    => $tax,
            'total'                  => $total,
            'currency'               => '₦',
        ];
    }

    /**
     * Peak hours (traffic)
     */
    private function isPeakHour(): bool
    {
        $time = Carbon::now()->format('H:i');
        return (
            ($time >= '06:30' && $time <= '09:30') ||
            ($time >= '16:30' && $time <= '20:00')
        );
    }

    /**
     * Night surcharge (9PM - 5AM)
     */
    private function isNightHour(): bool
    {
        $hour = Carbon::now()->hour;
        return ($hour >= 21 || $hour < 5);
    }

    /**
     * Weekend surcharge (Saturday & Sunday)
     */
    private function isWeekend(): bool
    {
        $day = Carbon::now()->dayOfWeek;
        return ($day === Carbon::SATURDAY || $day === Carbon::SUNDAY);
    }

    /**
     * Off-peak discount (Weekdays 11AM - 4PM)
     */
    private function isOffPeakDiscount(): bool
    {
        $hour = Carbon::now()->hour;
        $day = Carbon::now()->dayOfWeek;
        return ($day >= Carbon::MONDAY && $day <= Carbon::FRIDAY && $hour >= 11 && $hour <= 16);
    }

    /**
     *Check if today is a recognized holiday
     */
    private function isHoliday(): bool
    {
        $today = Carbon::now()->format('m-d');

        $holidays = [
            '01-01', // New Year’s Day
            '05-01', // Workers Day
            '10-01', // Independence Day
            '12-25', // Christmas Day
            '12-26', // Boxing Day
        ];

        // Add dynamic Easter Sunday check
        $easter = Carbon::createFromTimestamp(easter_date(Carbon::now()->year))->format('m-d');
        $holidays[] = $easter;

        return in_array($today, $holidays, true);
    }

    /**
     * Determine holiday surcharge factor
     */
    private function getHolidayFactor(): float
    {
        $today = Carbon::now()->format('m-d');

        return match ($today) {
            '12-25' => 1.5, // Christmas
            '01-01' => 1.25, // New Year
            default  => 1.25, // Other public holidays
        };
    }

    private function normalizeLocation(array $loc)
    {
        // already geocoded?
        if (isset($loc['lat'], $loc['lng'])) {
            return $loc;
        }

        // otherwise convert the address to lat/lng
        $geo = $this->googleMaps->geocodeAddress($loc['address']);

        return [
            'address' => $loc['address'],
            'lat'     => $geo['lat'],
            'lng'     => $geo['lng']
        ];
    }
}
