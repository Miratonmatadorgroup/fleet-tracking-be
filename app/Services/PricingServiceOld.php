<?php

namespace App\Services;

use App\Enums\DeliveryTypeEnums;
use App\Models\TransportPricing;
use App\Enums\TransportModeEnums;
use Illuminate\Support\Facades\Log;
use App\Models\TransportModePricing;

class PricingServiceOld
{

    protected OpenRouteService $ors;

    public function __construct(OpenRouteService $ors)
    {
        $this->ors = $ors;
    }


    public function calculate(string $mode, float $pachage_weight, string $pickup, string $dropoff, string $deliveryType): array
    {
        $baseRate = $this->getRateForLocations($pickup, $dropoff, $mode);

        Log::info("Base rate for $mode from $pickup to $dropoff: ₦{$baseRate}");

        $isInternational = $this->isInternational($pickup, $dropoff);

        $deliveryTypeFactor = match ($deliveryType) {
            DeliveryTypeEnums::QUICK->value => 1.5,
            DeliveryTypeEnums::NEXT_DAY->value => 1.2,
            default => 1,
        };

        $subtotal = $baseRate * $deliveryTypeFactor;

        if ($isInternational) {
            $importCharges = 0.35;
            $tax = round($subtotal * $importCharges, 2);
        } else {
            $vatRate = 0.075;
            $tax = round($subtotal * $vatRate, 2);
        }

        $total = round($subtotal + $tax, 2);
        $estimatedDays = $this->getEstimatedDays($deliveryType, $isInternational, $mode);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'is_international' => $isInternational,
            'estimated_days' => $estimatedDays,
        ];
    }


    protected function getRateForLocations(string $pickup, string $dropoff, string $mode): float
    {
        $distanceKm = $this->ors->getDistanceInKm($pickup, $dropoff);

        if (is_null($distanceKm) || $distanceKm == 0) {
            Log::warning("ORS returned null or 0 km distance between {$pickup} and {$dropoff}");
            $distanceKm = 10;
        }

        $pricing = TransportModePricing::where('mode', $mode)->first();
        $pricePerKm = $pricing?->price_per_km ?? 250; // Default if not set

        return round($distanceKm * $pricePerKm, 2);
    }


    protected function getRateForRoute(string $pickup, string $dropoff, string $mode): ?float
    {
        $pricing = TransportPricing::where('mode_of_transportation', $mode)
            ->whereRaw('LOWER(pickup_location) = ?', [strtolower($pickup)])
            ->whereRaw('LOWER(dropoff_location) = ?', [strtolower($dropoff)])
            ->first();

        return $pricing?->rate_per_route;
    }

    protected function getRatePerKg(string $mode): float
    {
        $pricing = TransportPricing::where('mode_of_transportation', $mode)->first();
        return $pricing ? $pricing->rate_per_kg : 200;  // Default fallback
    }

    public function isInternational(string $pickup, string $dropoff): bool
    {
        $pickup = strtolower($pickup);
        $dropoff = strtolower($dropoff);
        return !(str_contains($pickup, 'nigeria') && str_contains($dropoff, 'nigeria'));
    }

    public function getEstimatedDays(string $deliveryType, bool $isInternational, string $mode): int
    {
        if ($isInternational) {
            return match ($deliveryType) {
                DeliveryTypeEnums::QUICK->value => 3,
                DeliveryTypeEnums::NEXT_DAY->value => 5,
                default => ($mode === TransportModeEnums::AIR->value ? 7 : 10),
            };
        }

        return match ($deliveryType) {
            DeliveryTypeEnums::QUICK->value => 1,
            DeliveryTypeEnums::NEXT_DAY->value => 2,
            default => 3,
        };
    }
}
