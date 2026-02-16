<?php
namespace App\Notifications\User;

use App\Models\Driver;
use App\Models\TransportMode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverUnassignedFromTransportNotification extends Notification
{
    use Queueable;

    public Driver $driver;
    public TransportMode $transport;

    public function __construct(Driver $driver, TransportMode $transport)
    {
        $this->driver = $driver;
        $this->transport = $transport;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Transport Mode Unassignment',
            'message' => "Hi {$this->driver->name}, you have been unassigned from your transport mode ({$this->transport->model}, {$this->transport->registration_number}).",
            'driver_id' => $this->driver->id,
            'transport_mode_id' => $this->transport->id,
            'transport_mode_type' => $this->transport->type,
        ];
    }
}
