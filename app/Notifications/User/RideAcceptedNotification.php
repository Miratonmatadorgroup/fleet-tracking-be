<?php

namespace App\Notifications\User;

use App\Models\User;
use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RideAcceptedNotification extends Notification
{
    use Queueable;

    public RidePool $ride;
    public User $driver;

    public function __construct(RidePool $ride, User $driver)
    {
        $this->ride = $ride;
        $this->driver = $driver;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $vehicleType = $this->ride->transportMode?->type?->value ?? 'N/A';
        $manufacturer = $this->ride->transportMode?->manufacturer ?? 'N/A';
        $model = $this->ride->transportMode?->model ?? 'N/A';
        $registration = $this->ride->transportMode?->registration_number ?? 'N/A';

        $vehicleInfo =
            "Status: In Transit\n" .
            "Vehicle: " . ucfirst($vehicleType) . "\n" .
            "Model: " . $manufacturer . " " . $model . "\n" .
            "Plate: " . $registration . "\n";

        return [
            'title' => 'Driver Assigned',
            'message' => "Your LoopFreight ride has been accepted by {$this->driver->name} and is now heading to your pickup location!.\n" . $vehicleInfo,
            'ride_id' => $this->ride->id,
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
                'gender' => $this->driver->gender,
            ],
            'status' => 'in_transit',
        ];
    }
}
