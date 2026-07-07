<?php

namespace App\Notifications\User;

use App\Models\Asset;
use App\Models\Geofence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GeofenceAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Asset $asset,
        public Geofence $geofence
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [

            'title' => 'Geofence Alert',

            'message' =>
            "Your vehicle {$this->asset->license_plate} has entered the geofence \"{$this->geofence->name}\".",

            'asset' => [

                'id' => $this->asset->id,

                'license_plate' => $this->asset->license_plate,

                'imei' => $this->asset->imei,

            ],

            'geofence' => [

                'id' => $this->geofence->id,

                'name' => $this->geofence->name,

                'radius' => $this->geofence->radius_meters,

            ],

            'location' => [

                'latitude' => $this->asset->last_known_lat,

                'longitude' => $this->asset->last_known_lng,

            ],

            'triggered_at' => now()->toDateTimeString(),

        ];
    }
}
