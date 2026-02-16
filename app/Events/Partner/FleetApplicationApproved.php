<?php

namespace App\Events\Partner;

use App\Models\Driver;
use App\Models\TransportMode;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class FleetApplicationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Driver $driver,
        public readonly ?TransportMode $transport
    ) {}
}
