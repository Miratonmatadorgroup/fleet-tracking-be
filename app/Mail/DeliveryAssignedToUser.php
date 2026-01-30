<?php

namespace App\Mail;

use App\Models\Driver;
use App\Models\Payment;
use App\Models\Delivery;
use App\Models\TransportMode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeliveryAssignedToUser extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;
    public $driver;
    public $transport;
    public $payment;

    public function __construct(Delivery $delivery, Driver $driver, TransportMode $transport, Payment $payment)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
        $this->transport = $transport;
         $this->payment = $payment;
    }

    public function build()
    {
        return $this->subject('Your Delivery Has Been Assigned')
                    ->view('emails.delivery_assigned_to_user');
    }
}
