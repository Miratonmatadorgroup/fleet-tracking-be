<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RideBookedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;
    public mixed $ride;

    public function __construct(string $name, $ride)
    {
        $this->name = $name;
        $this->ride = $ride;
    }

    public function build()
    {
        return $this->subject('Your Ride Has Been Booked')
            ->view('emails.book_ride.ride_booked')
            ->with([
                'name' => $this->name,
                'ride' => $this->ride,
            ]);
    }
}
