<?php

namespace App\Actions\Ratings;

use App\Models\Delivery;
use App\Models\DriverRating;
use App\Enums\DeliveryStatusEnums;
use App\DTOs\Ratings\RateDriverDTO;
use App\Events\Ratings\DriverRated;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RateDriverAction
{
    public function execute(RateDriverDTO $dto): DriverRating
    {
        $delivery = Delivery::where('id', $dto->deliveryId)
            ->where('customer_id', $dto->customerId)
            ->where('status', DeliveryStatusEnums::COMPLETED->value)
            ->first();

        if (!$delivery) {
            throw new ModelNotFoundException("Delivery not found or not eligible for rating.");
        }

        $existingRating = DriverRating::where('delivery_id', $dto->deliveryId)
            ->where('customer_id', $dto->customerId)
            ->first();

        if ($existingRating) {

            throw new \Exception("You have already rated this driver for this delivery.");
        }

        $rating = DriverRating::updateOrCreate(
            [
                'delivery_id' => $dto->deliveryId,
                'customer_id' => $dto->customerId,
            ],
            [
                'driver_id' => $dto->driverId,
                'rating'    => $dto->rating,
                'comment'   => $dto->comment,
            ]
        );

        event(new DriverRated($rating));

        return $rating;
    }
}
