<?php

namespace App\Notifications;

use App\Models\RewardClaim;
use Illuminate\Bus\Queueable;
use App\Models\RewardCampaign;
use Illuminate\Notifications\Notification;

class RewardPaidNotification extends Notification
{
    use Queueable;

    public function __construct(public RewardClaim $claim) {}

    public function via($notifiable): array
    {
        return ['database']; // You can add 'mail', 'broadcast', etc.
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => "Your reward of ₦" . number_format((float) $this->claim->amount, 2) . " has been credited.",
            'claim_id' => $this->claim->id,
            'campaign' => $this->claim->campaign->name ?? 'Reward Campaign',
            'status' => $this->claim->status->value,
        ];
    }
}
