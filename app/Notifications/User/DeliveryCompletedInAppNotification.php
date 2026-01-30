<?php

namespace App\Notifications\User;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryCompletedInAppNotification extends Notification
{
    use Queueable;

    protected Delivery $delivery;
    protected string $role; // driver or customer

    public function __construct(Delivery $delivery, string $role)
    {
        $this->delivery = $delivery;
        $this->role = $role;
    }

    public function via(object $notifiable): array
    {
        return ['database']; // only in-app
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Delivery Completed',
            'message' => $this->role === 'driver'
                ? "Delivery (Tracking No: {$this->delivery->tracking_number}) has been confirmed completed by the customer."
                : "Your delivery (Tracking No: {$this->delivery->tracking_number}) has been confirmed completed successfully.",
            'tracking_number' => $this->delivery->tracking_number,
            'status' => $this->delivery->status->value,
            'role' => $this->role,
            'delivery_id' => $this->delivery->id,
            'time' => now()->toDateTimeString(),
        ];
    }
}
