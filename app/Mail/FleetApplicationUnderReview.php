<?php

// app/Mail/DriverUnderReview.php
namespace App\Mail;

use App\Models\Driver;
use App\Models\TransportMode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FleetApplicationUnderReview extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Driver $driver,
        public TransportMode $transport
    ) {}

    public function build()
    {
        return $this->subject('Fleet Application - Under Review')
            ->markdown('emails.partner.fleet_application_under_review', [
                'driver' => $this->driver,
                'transport' => $this->transport,
            ]);
    }
}

