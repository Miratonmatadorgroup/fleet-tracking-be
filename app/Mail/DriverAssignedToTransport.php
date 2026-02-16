<?php

namespace App\Mail;

use App\Models\Driver;
use App\Models\TransportMode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DriverAssignedToTransport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Driver $driver,
        public TransportMode $transport
    ) {}

    public function build()
    {
        return $this->subject('You have been assigned to a transport mode')
                    ->view('emails.driver.driver_assigned');
    }
}

