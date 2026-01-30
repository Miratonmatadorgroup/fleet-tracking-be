<?php
namespace App\Notifications\User;

use App\Models\Driver;
use App\Models\TransportMode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DriverAssignedToTransportNotification extends Notification
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
            'title' => 'Transport Mode Assignment',
            'message' => "Hi {$this->driver->name}, you have been assigned to a {$this->transport->type->value} ({$this->transport->manufacturer} {$this->transport->model}, Reg: {$this->transport->registration_number}).",
            'driver_id' => $this->driver->id,
            'transport_mode_id' => $this->transport->id,
            'type' => $this->transport->type->value,
            'manufacturer' => $this->transport->manufacturer,
            'model' => $this->transport->model,
            'registration_number' => $this->transport->registration_number,
            'year_of_manufacture' => $this->transport->year_of_manufacture,
            'color' => $this->transport->color,
            'passenger_capacity' => $this->transport->passenger_capacity,
            'max_weight_capacity' => $this->transport->max_weight_capacity,
        ];
    }
}
