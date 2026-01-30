<?php

namespace App\Notifications\User;

use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class DriverRideCancelledNotification extends Notification
{
    use Queueable;

    protected RidePool $ride;

    public function __construct(RidePool $ride)
    {
        $this->ride = $ride;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $user = $this->ride->user;

        return [
            'title'   => 'Ride Cancelled by User',
            'message' => "The passenger ({$user->first_name} {$user->last_name}) has cancelled the ride.",

            'ride_details' => [
                'reference_id'   => $this->ride->id,
                'passenger_name' => $user->first_name . ' ' . $user->last_name,
                'passenger_phone' => $user->mobile_number,

                'transport_mode' => ucfirst($this->ride->transportMode?->mode ?? 'N/A'),
                'pickup'         => $this->ride->pickup_location,
                'dropoff'        => $this->ride->dropoff_location ?? 'None',

                'estimated_cost' => '₦' . number_format($this->ride->estimated_cost, 2),

                'ride_date' => $this->ride->ride_date->format('d M, Y h:i A'),
                'status'    => 'Cancelled',
            ],
        ];
    }
}
