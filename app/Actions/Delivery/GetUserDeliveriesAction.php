<?php

namespace App\Actions\Delivery;

use App\Models\User;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

class GetUserDeliveriesAction
{
    public function execute(User $user, int $perPage = 10, ?string $search = null)
    {
        $query = Delivery::where('customer_id', $user->id)->latest();

        if (!empty($search)) {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('receiver_name', $likeOperator, "%{$search}%")
                    ->orWhere('receiver_phone', $likeOperator, "%{$search}%")
                    ->orWhere('sender_name', $likeOperator, "%{$search}%")
                    ->orWhere('package_type', $likeOperator, "%{$search}%")
                    ->orWhere('pickup_location', $likeOperator, "%{$search}%")
                    ->orWhere('dropoff_location', $likeOperator, "%{$search}%")
                    ->orWhere('status', $likeOperator, "%{$search}%")
                    ->orWhere('tracking_number', $likeOperator, "%{$search}%")
                    ->orWhere('id', $likeOperator, "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}
