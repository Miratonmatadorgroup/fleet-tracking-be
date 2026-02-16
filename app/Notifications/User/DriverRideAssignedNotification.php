<?php

namespace App\Notifications\User;

use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class DriverRideAssignedNotification extends Notification
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
            'title'   => 'New Ride Assigned',
            'message' => "A new ride has been booked and assigned to you.",

            'ride_details' => [
                'reference_id'   => $this->ride->id,

                'passenger_name'  => $user->first_name . ' ' . $user->last_name,
                'passenger_phone' => $user->mobile_number,

                'transport_mode' => ucfirst($this->ride->transportMode?->mode ?? 'N/A'),
                'pickup'         => $this->ride->pickup_location,
                'dropoff'        => $this->ride->dropoff_location ?? 'No dropoff selected',

                'estimated_cost' => '₦' . number_format($this->ride->estimated_cost, 2),

                'duration' => $this->ride->duration
                    ? $this->ride->duration . ' minutes'
                    : 'N/A',

                'ride_date' => $this->ride->ride_date->format('d M, Y h:i A'),
                'status'    => ucfirst($this->ride->status->value),
            ],
        ];
    }
}
