<?php

namespace App\Notifications\User;

use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RideCancelledByAdminNotification extends Notification
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
            'title'   => 'Your Ride Was Cancelled By Admin',
            'message' => 'An administrator has cancelled your ride. Any applicable refund has been processed.',

            'ride_details' => [
                'reference_id'       => $this->ride->id,
                'cancelled_by'       => 'Admin',
                'transport_mode'     => ucfirst($this->ride->transportMode?->mode ?? 'N/A'),
                'pickup'             => $this->ride->pickup_location,
                'dropoff'            => $this->ride->dropoff_location ?? 'No dropoff selected',

                'estimated_cost'     => '₦' . number_format($this->ride->estimated_cost, 2),
                'discount_applied'   => $this->ride->discount_cost
                    ? '₦' . number_format($this->ride->discount_cost, 2)
                    : 'None',

                'refund_amount'      => '₦' . number_format(
                    ($this->ride->estimated_cost + ($this->ride->discount_cost ?? 0)),
                    2
                ),

                'ride_date'          => $this->ride->ride_date
                    ? $this->ride->ride_date->format('d M, Y h:i A')
                    : 'N/A',

                'status'             => ucfirst($this->ride->status->value),
                'payment_status'     => ucfirst($this->ride->payment_status->value),
            ],
        ];
    }
}

