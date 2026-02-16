<?php



namespace App\Actions\RidePoolingPricing;

use App\Models\RidePoolingPricing;
use App\DTOs\RidePoolingPricing\CreateOrUpdateRidePoolingPricingDTO;

class CreateOrUpdateRidePoolingPricingAction
{
    public static function execute(CreateOrUpdateRidePoolingPricingDTO $dto): RidePoolingPricing
    {
        return RidePoolingPricing::updateOrCreate(
            ['category' => $dto->category],
            ['base_price' => $dto->base_price]
        );
    }
}
