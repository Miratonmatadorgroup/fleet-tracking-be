<?php

namespace App\Notifications\User;

use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RideBookedNotification extends Notification
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

            'title' => 'Your Ride Has Been Booked',
            'message' => 'Your ride request was successful. A driver will be in transit shortly.',


            'ride_details' => [
                'reference_id'   => $this->ride->id,

                'transport_mode' => ucfirst($this->ride->transportMode?->mode ?? 'N/A'),

                'added_fare_category' => $this->ride->ride_pool_category ?? 'None',

                'pickup'  => $this->ride->pickup_location,
                'dropoff' => $this->ride->dropoff_location ?? 'No dropoff selected',

                'estimated_cost' => '₦' . number_format($this->ride->estimated_cost, 2),

                'duration' => $this->ride->duration
                    ? $this->ride->duration . ' minutes'
                    : 'N/A',

                'ride_date' => $this->ride->ride_date->format('d M, Y h:i A'),

                'status' => ucfirst($this->ride->status->value),
                'payment_status' => ucfirst($this->ride->payment_status->value),
            ],
        ];
    }
}
