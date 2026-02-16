<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;

class DriverApplicationReceived extends Mailable
{
    public $driver;

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    public function build()
    {
        return $this->subject('Your Driver Application Has Been Received')
            ->view('emails.driver.application_received');
    }
}

