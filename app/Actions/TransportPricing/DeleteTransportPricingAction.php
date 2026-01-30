<?php
namespace App\Actions\TransportPricing;

use App\Models\TransportModePricing;

class DeleteTransportPricingAction
{
    public static function execute(TransportModePricing $pricing): bool
    {
        return $pricing->delete();
    }
}
