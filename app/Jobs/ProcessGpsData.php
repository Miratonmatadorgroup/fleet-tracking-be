<?php

namespace App\Jobs;

use App\Events\GpsDataReceived;
use App\Models\Asset;
use App\Models\GpsLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessGpsData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public Asset $asset,
        public array $data
    ) {
        $this->onQueue('gps');
    }

    public function handle(): void
    {
        DB::transaction(function () {
            // 1. Insert GPS Log
            $gpsLog = GpsLog::create([
                'asset_id' => $this->asset->id,
                'latitude' => $this->data['latitude'],
                'longitude' => $this->data['longitude'],
                'speed' => $this->data['speed'],
                'ignition' => $this->data['ignition'],
                'heading' => $this->data['heading'] ?? null,
                'altitude' => $this->data['altitude'] ?? null,
                'satellites' => $this->data['satellites'] ?? null,
                'hdop' => $this->data['hdop'] ?? null,
                'timestamp' => $this->data['timestamp'],
            ]);

            // 2. Update Asset Last Known Location
            $this->asset->update([
                'last_known_lat' => $this->data['latitude'],
                'last_known_lng' => $this->data['longitude'],
                'last_ping_at' => $this->data['timestamp'],
                'status' => $this->data['speed'] > 0 ? 'active' : 'idle',
            ]);

            // 3. Update Redis Cache (TTL: 5 minutes)
            Cache::put(
                "asset:{$this->asset->id}:location",
                [
                    'lat' => $this->data['latitude'],
                    'lng' => $this->data['longitude'],
                    'speed' => $this->data['speed'],
                    'ignition' => $this->data['ignition'],
                    'timestamp' => $this->data['timestamp'],
                ],
                now()->addMinutes(5)
            );

            // 4. Trigger Event (for geofence checking, etc.)
            event(new GpsDataReceived($this->asset, $gpsLog));

            // 5. Broadcast to WebSocket (Real-time Update)
            broadcast(new \App\Events\AssetLocationUpdated($this->asset, $this->data))->toOthers();
        });
    }
}