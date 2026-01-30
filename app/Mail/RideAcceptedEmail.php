<?php

namespace App\Mail;

use App\Models\User;
use App\Models\RidePool;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class RideAcceptedEmail extends Mailable
{
    public User $user;
    public RidePool $ride;
    public User $driver;

    public function __construct(User $user, RidePool $ride, User $driver)
    {
        $this->user = $user;
        $this->ride = $ride;
        $this->driver = $driver;
    }

    public function build()
    {
        return $this->subject("Your Driver is on the Way")
            ->view('emails.driver.ride_accepted')
            ->with([
                'user' => $this->user,
                'ride' => $this->ride,
                'driver' => $this->driver,
            ]);
    }
}
