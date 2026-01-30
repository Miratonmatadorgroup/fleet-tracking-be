<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\RewardCampaign;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class RewardAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $driver,
        public RewardCampaign $campaign
    ) {}

    public function build(): self
    {
        return $this->subject("You’ve unlocked a reward on LoopFreight!")
            ->view('emails.rewards.available')
            ->with([
                'name' => $this->driver->name,
                'reward_amount' => $this->campaign->reward_amount,
                'campaign_name' => $this->campaign->name,
            ]);
    }
}
