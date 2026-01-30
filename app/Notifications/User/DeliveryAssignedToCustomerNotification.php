<?php
namespace App\Notifications\User;

use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryAssignedToCustomerNotification extends Notification
{
    use Queueable;

    public Delivery $delivery;
    public Driver $driver;
    public string $vehicle;
    public string $contacts;

    public function __construct(Delivery $delivery, Driver $driver, string $vehicle, string $contacts)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
        $this->vehicle = $vehicle;
        $this->contacts = $contacts;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Delivery Assigned',
            'message' => "Your delivery (Tracking: {$this->delivery->tracking_number}) has been assigned to driver {$this->driver->name}. Vehicle: {$this->vehicle}. Contact: {$this->contacts}",
            'delivery_id' => $this->delivery->id,
            'tracking_number' => $this->delivery->tracking_number,
            'driver_phone' => $this->driver->phone,
            'driver_email' => $this->driver->email,
            'driver_whatsapp_number' => $this->driver->whatsapp_number,
            'driver_name' => $this->driver->name,
        ];
    }
}
