<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Dispute;

class DisputeSubmittedNotification extends Notification
{
    use Queueable;

    protected Dispute $dispute;

    public function __construct(Dispute $dispute)
    {
        $this->dispute = $dispute;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Dispute Submitted',
            'message' => "Your dispute titled \"{$this->dispute->title}\" has been submitted successfully. We’ll review it shortly.",
            'dispute_id' => $this->dispute->id,
            'status' => $this->dispute->status->value,
            'time' => now()->toDateTimeString(),
        ];
    }
}
