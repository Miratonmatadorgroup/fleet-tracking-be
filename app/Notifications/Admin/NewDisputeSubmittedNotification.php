<?php
namespace App\Notifications\Admin;

use App\Models\Dispute;
use Illuminate\Notifications\Notification;

class NewDisputeSubmittedNotification extends Notification
{
    public Dispute $dispute;

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
            'title' => 'New Dispute Submitted',
            'message' => "{$this->dispute->user->name} has submitted a new dispute.",
            'dispute_id' => $this->dispute->id,
            'user_id' => $this->dispute->user_id,
            'submitted_at' => now(),
        ];
    }
}
