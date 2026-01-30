<?php

namespace App\Actions\Driver;

use App\Models\Delivery;
use App\Models\DriverRating;
use App\DTOs\Driver\DriverDeliveryCountsDTO;
use App\Events\Driver\DriverDeliveryCountsFetchedEvent;

class GetDriverDeliveryCountsAction
{
    public function execute(DriverDeliveryCountsDTO $dto): array
    {
        $counts = Delivery::where('driver_id', $dto->driver->id)
            ->selectRaw("
                SUM(CASE WHEN status IN ('in_transit', 'delivered') THEN 1 ELSE 0 END) as total_active_deliveries,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as total_completed_deliveries
            ")
            ->first();

        $averageRating = DriverRating::where('driver_id', $dto->driver->user_id)->avg('rating');
        $averageRating = $averageRating ? round($averageRating, 2) : null;

        $result = [
            'total_active_deliveries' => (int) $counts->total_active_deliveries,
            'total_completed_deliveries' => (int) $counts->total_completed_deliveries,
            'average_rating'            => $averageRating,
        ];

        new DriverDeliveryCountsFetchedEvent($dto->driver, $result);

        return $result;
    }
}
