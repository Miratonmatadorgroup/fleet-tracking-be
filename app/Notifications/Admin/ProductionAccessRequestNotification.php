<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionAccessRequestNotification extends Notification
{
    public $user;
    public $request;

    public function __construct($user, $request)
    {
        $this->user = $user;
        $this->request = $request;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Production Access Request',
            'message' => "{$this->user->name} has requested production access.",
            'user_id' => $this->user->id,
            'request_id' => $this->request->id,
            'app_type' => $this->request->app_type,
            'status' => $this->request->status,
        ];
    }
}

