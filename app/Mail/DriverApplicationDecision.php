<?php
namespace App\Mail;

use App\Models\Driver;
use Illuminate\Mail\Mailable;

class DriverApplicationDecision extends Mailable
{
    public Driver $driver;
    public string $status;

    public function __construct(Driver $driver, string $action)
    {
        $this->driver = $driver;
        $this->status = $action === 'approve' ? 'approved' : 'rejected';
    }

    public function build()
    {
        return $this->subject("Your Driver Application Has Been ".ucfirst($this->status))
            ->view('emails.driver.application_decision')
             ->with([
                'driver' => $this->driver,
                'status' => $this->status,
             ]);
    }
}
