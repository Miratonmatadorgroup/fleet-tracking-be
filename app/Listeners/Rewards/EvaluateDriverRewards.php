<?php

namespace App\Listeners\Rewards;

use App\Models\User;
use App\Models\RewardClaim;
use App\Models\RewardCampaign;
use App\Models\RewardDeliveryLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Enums\RewardCampaignTypeEnums;
use App\Events\Delivery\DeliveryCompleted;


class EvaluateDriverRewards
{
    public function handle(DeliveryCompleted $event)
    {
        $delivery = $event->delivery;

        Log::info("DeliveryCompleted event fired for delivery {$delivery->id}");

        if (!$delivery->driver_id || !$delivery->driver?->user) {
            Log::warning("Delivery {$delivery->id} has no valid driver assigned.");
            return;
        }

        $driverUser = $delivery->driver->user;

        if (!$driverUser || !$driverUser->hasRole('driver')) {
            Log::warning("User not found or not a valid driver for delivery {$delivery->id}");
            return;
        }

        $campaigns = RewardCampaign::where('active', true)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->get();

        Log::info("Found {$campaigns->count()} active campaigns for driver user ID {$driverUser->id}");

        foreach ($campaigns as $campaign) {
            $weight = $this->weightForDistance($delivery->distance_km, $campaign->meta['weighting'] ?? []);
            $earning = $delivery->total_price ?? 0.0;

            Log::info("Logging delivery for campaign {$campaign->id} – weight: {$weight}, earning: ₦{$earning}");

            $alreadyLogged = RewardDeliveryLog::where('reward_campaign_id', $campaign->id)
                ->where('delivery_id', $delivery->id)
                ->exists();

            if ($alreadyLogged) {
                Log::warning("Duplicate reward log prevented for delivery {$delivery->id} and campaign {$campaign->id}");
                continue;
            }

            RewardDeliveryLog::create([
                'reward_campaign_id' => $campaign->id,
                'driver_id' => $driverUser->id, // user_id
                'delivery_id' => $delivery->id,
                'distance_km' => $delivery->distance_km,
                'weighted_count' => $weight,
                'delivery_earning' => $earning,
            ]);

            $this->evaluateAndNotifyIfTargetHit($campaign, $driverUser);
        }
    }

    private function weightForDistance($distance, $weighting)
    {
        $distance = floatval($distance ?: 0);
        foreach ($weighting as $w) {
            if ($distance >= $w['min_km'] && $distance <= $w['max_km']) {
                return floatval($w['weight']);
            }
        }
        return 1.0;
    }

    private function evaluateAndNotifyIfTargetHit(RewardCampaign $campaign, User $driverUser)
    {
        $meta = $campaign->meta ?? [];
        $required = $meta['deliveries_required'] ?? null;
        if (!$required) return;

        $sum = RewardDeliveryLog::where('reward_campaign_id', $campaign->id)
            ->where('driver_id', $driverUser->id)
            ->sum('weighted_count');

        $totalEarnings = RewardDeliveryLog::where('reward_campaign_id', $campaign->id)
            ->where('driver_id', $driverUser->id)
            ->sum('delivery_earning');

        $avgRating = DB::table('driver_ratings')
            ->where('driver_id', $driverUser->id)
            ->avg('rating') ?? 0.0;

        $minRating = $meta['min_rating'] ?? 0;
        $minEarnings = $meta['min_earnings'] ?? 0;

        Log::info("Evaluating reward conditions for driver user ID {$driverUser->id} on campaign {$campaign->id}", [
            'required_deliveries' => $required,
            'actual_weighted_sum' => $sum,
            'avg_rating' => $avgRating,
            'min_rating' => $minRating,
            'total_earnings' => $totalEarnings,
            'min_earnings' => $minEarnings,
        ]);

        if ($sum >= $required && $avgRating >= $minRating && $totalEarnings >= $minEarnings) {
           
            $claimPeriod = $this->getClaimPeriod($campaign);

            // Log claim period
            Log::info("Using claim period {$claimPeriod} for campaign {$campaign->id} and driver {$driverUser->id}");


            $existingClaim = RewardClaim::where('reward_campaign_id', $campaign->id)
                ->where('driver_id', $driverUser->id)
                ->when($campaign->type !== \App\Enums\RewardCampaignTypeEnums::MILESTONE->value, function ($q) use ($claimPeriod) {
                    $q->where('claim_period', $claimPeriod);
                })
                ->first();


            if (!$existingClaim) {
                RewardClaim::create([
                    'reward_campaign_id' => $campaign->id,
                    'driver_id' => $driverUser->id,
                    'claim_period' => $claimPeriod,
                    'amount' => $campaign->reward_amount,
                    'status' => 'pending'
                ]);

                app(NotificationService::class)->notifyRewardAvailable($driverUser, $campaign);
                Log::info("Reward claim created for driver user ID {$driverUser->id} on campaign {$campaign->id}");
            } else {
                Log::info("Reward already claimed for driver user ID {$driverUser->id} on campaign {$campaign->id}");
            }
        } else {
            Log::info("Driver user ID {$driverUser->id} did not meet reward criteria for campaign {$campaign->id}");
        }
    }


    private function getClaimPeriod(RewardCampaign $campaign): string
    {
        $now = now();

        return match ($campaign->type) {
            \App\Enums\RewardCampaignTypeEnums::DAILY => $now->toDateString(),
            \App\Enums\RewardCampaignTypeEnums::WEEKLY => $now->startOfWeek()->toDateString(),
            \App\Enums\RewardCampaignTypeEnums::MONTHLY => $now->startOfMonth()->toDateString(),
            \App\Enums\RewardCampaignTypeEnums::MILESTONE => 'milestone',
            default => $now->toDateString()
        };
    }
}
