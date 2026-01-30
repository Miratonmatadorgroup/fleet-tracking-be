<?php
namespace App\Events\Rewards;

use App\Models\RewardCampaign;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RewardCampaignCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public RewardCampaign $campaign) {}
}
