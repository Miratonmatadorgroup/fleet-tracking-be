<?php
namespace App\Mail;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\TransportMode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DriverReassignedToUser extends Mailable
{
    use Queueable, SerializesModels;

    public $delivery;
    public $driver;
    public $transport;

    public function __construct(Delivery $delivery, Driver $driver, TransportMode $transport)
    {
        $this->delivery = $delivery;
        $this->driver = $driver;
        $this->transport = $transport;
    }

    public function build()
    {
        return $this->subject('Your Delivery Driver Has Been Changed')
            ->view('emails.driver.driver_reassigned_to_user');
    }
}

