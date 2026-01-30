<?php
namespace App\Events\Driver;

use App\Models\Driver;
use Illuminate\Queue\SerializesModels;

class DriverDeliveryCountsFetchedEvent
{
    use SerializesModels;

    public function __construct(
        public readonly Driver $driver,
        public readonly array $counts
    ) {}
}

