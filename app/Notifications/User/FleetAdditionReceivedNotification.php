<?php


namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FleetAdditionReceivedNotification extends Notification
{
    use Queueable;

    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Fleet Addition Submitted',
            'message' => "Hi {$this->name}, your fleet addition has been received. We’ll review and get back to you shortly.",
            'time' => now()->toDateTimeString(),
        ];
    }
}
