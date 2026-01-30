<?php

namespace App\Notifications\User;

use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class DriverRejectedRideNotification extends Notification
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
        return [
            'title'   => 'Ride Rejection Confirmed',
            'message' => 'You have successfully rejected a ride. You are now set to available status.',

            'ride_details' => [
                'reference_id'   => $this->ride->id,
                'pickup'         => $this->ride->pickup_location,
                'dropoff'        => $this->ride->dropoff_location ?? 'None',
                'estimated_cost' => '₦' . number_format($this->ride->estimated_cost, 2),
                'ride_date'      => $this->ride->ride_date->format('d M, Y h:i A'),
                'status'         => 'Rejected',
            ],
        ];
    }
}
