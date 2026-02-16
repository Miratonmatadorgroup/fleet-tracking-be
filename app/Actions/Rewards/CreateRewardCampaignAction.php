<?php
namespace App\Actions\Rewards;

use App\Models\RewardCampaign;
use App\DTOs\Rewards\RewardCampaignDataDTO;
use App\Events\Rewards\RewardCampaignCreated;

class CreateRewardCampaignAction
{
    public function execute(RewardCampaignDataDTO $data): RewardCampaign
    {
       $campaign = RewardCampaign::create([
            'name' => $data->name,
            'type' => $data->type,
            'reward_amount' => $data->reward_amount,
            'starts_at' => $data->starts_at,
            'ends_at' => $data->ends_at,
            'meta' => $data->meta,
            'active' => false, // default to off
        ]);

        //Fire the event right here
        event(new RewardCampaignCreated($campaign));

        return $campaign;

    }
}
