<?php
namespace App\Actions\TransportPricing;

use Illuminate\Support\Collection;
use App\Models\TransportModePricing;

class GetAllTransportPricingAction
{
    public static function execute(): Collection
    {
        return TransportModePricing::all();
    }
}
