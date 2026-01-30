<?php
namespace App\Notifications\Admin;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewPartnerApplicationNotification extends Notification
{
    public $partner;

    public function __construct($partner)
    {
        $this->partner = $partner;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Partner Application',
            'message' => "{$this->partner->name} has submitted a new partner application.",
            'partner_id' => $this->partner->id,
        ];
    }
}

