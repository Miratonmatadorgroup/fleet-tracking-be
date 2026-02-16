<?php
namespace App\Actions\TransportPricing;

use App\Models\TransportModePricing;
use App\DTOs\TransportPricing\UpdateTransportPricingDTO;

class UpdateTransportPricingAction
{
    public static function execute(UpdateTransportPricingDTO $dto): TransportModePricing
    {
        return TransportModePricing::updateOrCreate(
            ['mode' => $dto->mode],
            ['price_per_km' => $dto->price_per_km]
        );
    }
}
