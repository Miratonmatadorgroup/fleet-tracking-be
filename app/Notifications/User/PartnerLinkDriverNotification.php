<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerLinkDriverNotification extends Notification
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
            'title' => 'Partner Link Driver',
            'message' => "Hi {$this->name}, a new partner has linked you to a transport mode.",
            'time' => now()->toDateTimeString(),
        ];
    }
}
