<?php
namespace App\Notifications\Admin;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewDriverApplicationNotification extends Notification
{
    public $driver;

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Driver Application',
            'message' => "{$this->driver->name} has submitted a new driver application.",
            'driver_id' => $this->driver->id,
        ];
    }
}

