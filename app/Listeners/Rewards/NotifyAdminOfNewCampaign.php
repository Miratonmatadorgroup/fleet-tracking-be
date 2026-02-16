<?php
namespace App\Listeners\Rewards;

use Illuminate\Support\Facades\Log;
use App\Events\Rewards\RewardCampaignCreated;

class NotifyAdminOfNewCampaign
{
    public function handle(RewardCampaignCreated $event): void
    {
        $campaign = $event->campaign;

        Log::info("New Reward Campaign Created: {$campaign->name} ({$campaign->id})");

    }
}
