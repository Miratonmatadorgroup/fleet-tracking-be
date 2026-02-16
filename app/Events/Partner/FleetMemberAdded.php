<?php
namespace App\Events\Partner;


use App\Models\Driver;
use App\Models\Partner;
use App\Models\TransportMode;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class FleetMemberAdded
{
     use Dispatchable;
    public function __construct(
        public readonly Partner $partner,
        public readonly Driver $driver,
        public readonly TransportMode $transport,
        public readonly User $partnerUser
    ) {}
}
