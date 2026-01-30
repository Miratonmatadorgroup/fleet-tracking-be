<?php

namespace App\Mail;

use App\Models\User;
use App\Models\RewardClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class RewardPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $driver,
        public RewardClaim $claim
    ) {}

    public function build()
    {
        return $this
            ->subject("You've Received Your Reward!")
            ->view('emails.rewards.paid')
            ->with([
                'name' => $this->driver->name,
                'campaign_name' => $this->claim->campaign->title ?? 'Reward Campaign',
                'reward_amount' => $this->claim->amount,
            ]);
    }
}
