<?php
namespace App\Actions\Rewards;

use Exception;
use App\Models\User;
use App\Models\RewardClaim;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\DTOs\Rewards\ClaimRewardDTO;
use App\Services\NotificationService;

class ClaimRewardAction
{
    public function execute(ClaimRewardDTO $dto): RewardClaim
    {
        return DB::transaction(function () use ($dto) {
            $claim = RewardClaim::where('id', $dto->claim_id)
                ->where('driver_id', $dto->driver_id)
                ->where('status', 'pending')
                ->firstOrFail();

            $campaign = $claim->campaign;

            if (!$campaign->active || ($campaign->ends_at && now()->gt($campaign->ends_at))) {
                throw new Exception("This campaign is no longer active.");
            }

            $driver = User::findOrFail($dto->driver_id);

            // Credit Wallet
            $tx = WalletService::creditReward($driver, $claim->amount, [
                'campaign_id' => $campaign->id,
                'claim_id' => $claim->id,
            ]);

            // Update claim
            $claim->update([
                'status' => 'paid',
                'wallet_transaction_id' => $tx['transaction_id'],
            ]);

            // Notify driver
            app(NotificationService::class)->notifyRewardPaid($driver, $claim);

            return $claim;
        });
    }
}
