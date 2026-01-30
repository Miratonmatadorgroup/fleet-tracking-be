<?php

namespace App\Actions\BookRide;

use App\Models\RidePool;
use App\Models\DriverRating;
use App\Enums\RidePoolStatusEnums;
use App\DTOs\BookRide\RateRidePoolDriverDTO;

use App\Events\BookRide\RidePoolDriverRated;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RateRidePoolDriverAction
{
    public function execute(RateRidePoolDriverDTO $dto): DriverRating
    {
        $ride = RidePool::where('id', $dto->ridePoolId)
            ->where('user_id', $dto->customerId)
            ->whereIn('status', [
                RidePoolStatusEnums::COMPLETED->value,
                RidePoolStatusEnums::RIDE_ENDED->value
            ])
            ->first();

        if (!$ride) {
            throw new ModelNotFoundException("Ride not found or not eligible for rating.");
        }

        $existingRating = DriverRating::where('ride_pool_id', $dto->ridePoolId)
            ->where('customer_id', $dto->customerId)
            ->first();

        if ($existingRating) {
            throw new \Exception("You have already rated this driver for this ride.");
        }

        $rating = DriverRating::create([
            'ride_pool_id' => $dto->ridePoolId,
            'customer_id'  => $dto->customerId,
            'driver_id'    => $dto->driverId,
            'rating'       => $dto->rating,
            'comment'      => $dto->comment,
        ]);

        $ride->update(['rated' => true]);

        event(new RidePoolDriverRated($rating));

        return $rating;
    }

}
