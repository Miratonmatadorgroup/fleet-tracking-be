<?php
namespace App\Events\Partner;

use App\Models\User;
use App\Models\Driver;
use App\Models\Partner;
use App\Models\TransportMode;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class PartnerApplicationSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Partner $partner,
        public Driver $driver,
        public TransportMode $transport
    ) {}
}
