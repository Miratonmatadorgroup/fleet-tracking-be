<?php
namespace App\Notifications\User;


use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Enums\DisputeStatusEnums;

class DisputeStatusUpdatedNotification extends Notification
{
    use Queueable;

    protected DisputeStatusEnums $status;
    protected string $title;

    public function __construct(DisputeStatusEnums $status, string $title)
    {
        $this->status = $status;
        $this->title = $title;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Dispute Status Update',
            'message' => "Your dispute titled \"{$this->title}\" has been updated to \"{$this->status->value}\".",
            'status' => $this->status->value,
            'dispute_title' => $this->title,
        ];
    }
}
