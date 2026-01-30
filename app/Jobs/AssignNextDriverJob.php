<?php

namespace App\Jobs;

use App\Models\RidePool;
use App\Services\RideSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AssignNextDriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $rideId;

    public function __construct(string $rideId)
    {
        $this->rideId = $rideId;
    }

    public function handle(): void
    {
        $ride = RidePool::find($this->rideId);

        if (! $ride) {
            return;
        }

        // If driver already accepted, stop
        if ($ride->driver_accepted_at !== null) {
            return;
        }

        // Find next driver
        $nextDriver = app(RideSearchService::class)
            ->findNearestAvailableDriver(
                lat: $ride->pickup_latitude,
                lng: $ride->pickup_longitude,
                modeId: $ride->transport_mode_id
            );

        // If no driver found, schedule retry after 10 min
        if (! $nextDriver) {
            self::dispatch($ride->id)->delay(now()->addMinutes(10));
            return;  // <===== IMPORTANT
        }

        // Assign new driver
        $ride->driver_id   = $nextDriver->id;
        $ride->partner_id  = $nextDriver->transportMode->partner_id;
        $ride->save();

        // Schedule next retry AFTER ASSIGNMENT
        self::dispatch($ride->id)->delay(now()->addMinutes(10));
        return;  // <===== IMPORTANT
    }
}
