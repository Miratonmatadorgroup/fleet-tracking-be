<?php

namespace App\Actions\Driver;

use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use App\DTOs\Driver\AssignedDeliveriesDTO;

class GetAssignedDeliveriesAction
{
    public function execute(AssignedDeliveriesDTO $dto, int $perPage = 10, ?string $search = null)
    {
        $query = Delivery::with('transportMode')
            ->where('driver_id', $dto->driver->id)
            ->latest();

        $driver = DB::getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        if (!empty($search)) {
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('tracking_number', $likeOperator, "%{$search}%")
                    ->orWhere('receiver_name', $likeOperator, "%{$search}%")
                    ->orWhere('sender_name', $likeOperator, "%{$search}%")
                    ->orWhere('pickup_location', $likeOperator, "%{$search}%")
                    ->orWhere('dropoff_location', $likeOperator, "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}

