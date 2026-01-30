<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\RewardCampaign;
use Illuminate\Notifications\Notification;

class RewardAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(public RewardCampaign $campaign) {}

    public function via($notifiable)
    {
        return ['database']; // or ['mail', 'database', 'sms'] if needed
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'You’ve unlocked a reward!',
            'body' => "You've met the target for the '{$this->campaign->name}' campaign. Reward: ₦{$this->campaign->reward_amount}",
            'campaign_id' => $this->campaign->id,
        ];
    }
}
