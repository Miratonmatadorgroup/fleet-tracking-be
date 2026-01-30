<?php
namespace App\Actions\Rewards;

use App\Models\RewardCampaign;
use App\DTOs\Rewards\RewardCampaignDataDTO;

class UpdateRewardCampaignAction
{
    public function execute(RewardCampaign $campaign, RewardCampaignDataDTO $data): RewardCampaign
    {
        $campaign->update([
            'name' => $data->name,
            'type' => $data->type,
            'reward_amount' => $data->reward_amount,
            'starts_at' => $data->starts_at,
            'ends_at' => $data->ends_at,
            'meta' => $data->meta,
        ]);

        return $campaign->refresh();
    }
}
