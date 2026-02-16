<?php

namespace App\Actions\Driver;

use App\Models\RidePool;
use App\DTOs\Driver\FetchDriverBookedRidesDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class FetchDriverBookedRidesAction
{
    public function execute(string $driverId, FetchDriverBookedRidesDTO $dto): LengthAwarePaginator
    {
        $query = RidePool::query()
        ->where('driver_id', $driverId)
            ->orderByDesc('created_at');

        // --- Optional SEARCH ---
        if (!empty($dto->search)) {
            $search = strtolower(trim($dto->search));

            $query->where(function ($q) use ($search) {

                $q->orWhereRaw('LOWER(ride_pool_category) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(payment_status) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('CAST(estimated_cost AS CHAR) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(pickup_location, '$.address')) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(dropoff_location, '$.address')) LIKE ?", ["%{$search}%"]);
            });
        }

        return $query->paginate(
            $dto->perPage,
            ['*'],
            'page',
            $dto->page
        );
    }
}
