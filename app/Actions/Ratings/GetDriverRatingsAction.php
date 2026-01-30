<?php

namespace App\Actions\Ratings;

use App\Models\DriverRating;
use App\DTOs\Ratings\GetDriverRatingsDTO;

class GetDriverRatingsAction
{
    public function execute(GetDriverRatingsDTO $dto)
    {
        return DriverRating::with(['customer', 'delivery'])
            ->where('driver_id', $dto->driverId)
            ->latest()
            ->paginate($dto->perPage);
    }
}
