<?php

namespace App\Notifications\User;

use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomerNoDriverYetNotification extends Notification
{
    use Queueable;

    public function __construct(public Delivery $delivery) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $date = \Carbon\Carbon::parse($this->delivery->delivery_date)->format('d M, Y');
        $time = \Carbon\Carbon::parse($this->delivery->delivery_time)->format('H:i');
        $amount = number_format((float) $this->delivery->total_price, 2);

        $customerName = $this->delivery->customer->name
            ?? $this->delivery->customer_name
            ?? 'Customer';

        $paymentMsg = "Hi {$customerName}, your payment of ₦{$amount} was successful.";

        return [
            'title' => 'Awaiting Driver Assignment',
            'message' => "{$paymentMsg} Your delivery (#{$this->delivery->tracking_number},{$this->delivery->waybill_number}) is awaiting driver assignment. We’ll notify you once a driver is available.",

            'delivery_id'       => $this->delivery->id,
            'tracking_number'   => $this->delivery->tracking_number,
            'pickup_location'   => $this->delivery->pickup_location,
            'dropoff_location'  => $this->delivery->dropoff_location,
            'delivery_date'     => $this->delivery->delivery_date,
            'delivery_time'     => $this->delivery->delivery_time,
            'amount_paid'       => $this->delivery->total_price,

            'time' => now()->toDateTimeString(),
        ];
    }
}
