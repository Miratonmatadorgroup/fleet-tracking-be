<?php
namespace App\Mail;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FleetApplicationDecision extends Mailable
{
    use Queueable, SerializesModels;

    public Driver $driver;
    public string $status;

    public function __construct(Driver $driver, string $action)
    {
        $this->driver = $driver;
        $this->status = $action === 'approve' ? 'approved' : 'rejected';
    }

    public function build()
    {
        return $this->markdown('emails.partner.fleet_application_decision')
            ->subject("Fleet Application {$this->status}")
            ->with([
                'driver' => $this->driver,
                'status' => $this->status,
                'transport' => $this->driver->transportModeDetails,
                'partner' => $this->driver->transportModeDetails->partner
            ]);
    }
}
