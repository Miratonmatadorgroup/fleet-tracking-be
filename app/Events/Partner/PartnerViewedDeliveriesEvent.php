<?php
namespace App\Events\Partner;

use Illuminate\Foundation\Events\Dispatchable;

class PartnerViewedDeliveriesEvent
{
    use Dispatchable;

    public function __construct(public string $partnerId) {}
}
