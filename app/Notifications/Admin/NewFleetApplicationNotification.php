<?php
namespace App\Notifications\Admin;

use App\Models\Driver;
use App\Models\TransportMode;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewFleetApplicationNotification extends Notification
{
    public $driver;
    public $transport;
    public $partner;

    public function __construct(Driver $driver, TransportMode $transport, User $partner)
    {
        $this->driver = $driver;
        $this->transport = $transport;
        $this->partner = $partner;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Fleet Application',
            'message' => 'New driver and transport added by partner requires approval',
            'driver_id' => $this->driver->id,
            'transport_id' => $this->transport->id,
            'partner_id' => $this->partner->id,
        ];
    }
}
