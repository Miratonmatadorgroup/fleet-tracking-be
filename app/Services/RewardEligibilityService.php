<?php

namespace App\Services;

use App\Models\User;
use App\Models\RewardCampaign;
use App\Enums\DeliveryStatusEnums;

class RewardEligibilityService
{
    public function check(User $driver, RewardCampaign $campaign): bool
    {
        $meta = $campaign->meta;

        $deliveries = $driver->deliveries()
            ->where('status', DeliveryStatusEnums::COMPLETED)
            ->whereBetween('updated_at', [$campaign->starts_at, $campaign->ends_at])
            ->get();


        $weightedCount = 0;
        $totalEarnings = 0;

        foreach ($deliveries as $delivery) {
            $distance = $delivery->distance_km;
            $weight = $this->getWeight($distance, $meta['weighting']);
            $weightedCount += $weight;
            $totalEarnings += $delivery->amount;
        }

        $avgRating = $driver->average_rating;

        return $weightedCount >= $meta['deliveries_required']
            && $avgRating >= $meta['min_rating']
            && $totalEarnings >= $meta['min_earnings'];
    }

    private function getWeight(float $distance, array $weightingRules): float
    {
        foreach ($weightingRules as $rule) {
            if ($distance >= $rule['min_km'] && $distance < $rule['max_km']) {
                return $rule['weight'];
            }
        }

        return 1.0; // default weight
    }
}
