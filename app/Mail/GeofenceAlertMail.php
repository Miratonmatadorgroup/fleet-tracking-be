<?php

namespace App\Mail;


use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Asset;
use App\Models\Geofence;

class GeofenceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $asset,
        public $geofence,
        public string $eventType
    ) {}

    public function build()
    {
        $subject = $this->eventType === 'entry'
            ? 'Geofence Entry Alert - ' . $this->asset->license_plate
            : 'Geofence Exit Alert - ' . $this->asset->license_plate;

        return $this
            ->subject($subject)
            ->view('emails.geofence-alert')
            ->with([
                'user' => $this->user,
                'asset' => $this->asset,
                'geofence' => $this->geofence,
                'eventType' => $this->eventType,
            ]);
    }
}
