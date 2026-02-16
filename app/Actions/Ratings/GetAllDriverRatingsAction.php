<?php

namespace App\Actions\Ratings;

use App\Models\DriverRating;
use Illuminate\Support\Facades\DB;


class GetAllDriverRatingsAction
{
    public function execute(int $perPage = 10, ?string $search = null)
    {
        $query = DriverRating::with(['driver', 'customer', 'delivery'])->latest();

        if (!empty($search)) {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator) {

                $q->where('id', $likeOperator, "%{$search}%")
                    ->orWhere('rating', $likeOperator, "%{$search}%")
                    ->orWhere('comment', $likeOperator, "%{$search}%");

                $q->orWhereHas('driver', function ($dq) use ($search, $likeOperator) {
                    $dq->where('name', $likeOperator, "%{$search}%")
                        ->orWhere('email', $likeOperator, "%{$search}%")
                        ->orWhere('phone', $likeOperator, "%{$search}%");
                });

                $q->orWhereHas('customer', function ($cq) use ($search, $likeOperator) {
                    $cq->where('name', $likeOperator, "%{$search}%")
                        ->orWhere('email', $likeOperator, "%{$search}%")
                        ->orWhere('phone', $likeOperator, "%{$search}%");
                });

                $q->orWhereHas('delivery', function ($dq) use ($search, $likeOperator) {
                    $dq->where('tracking_number', $likeOperator, "%{$search}%")
                        ->orWhere('receiver_name', $likeOperator, "%{$search}%")
                        ->orWhere('sender_name', $likeOperator, "%{$search}%");
                });
            });
        }

        return $query->paginate($perPage);
    }
}
