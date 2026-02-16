<?php

namespace App\Actions\Partner;

use App\Models\Delivery;
use App\DTOs\Partner\ViewPartnerDeliveriesDTO;

class ViewPartnerDeliveriesAction
{
    public function execute(ViewPartnerDeliveriesDTO $dto, ?string $search = null)
    {
        $query = Delivery::whereIn('driver_id', function ($query) use ($dto) {
            $query->select('driver_id')
                ->from('transport_modes')
                ->where('partner_id', $dto->partner->id);
        });

        // Apply search filter if provided
        if (!empty($search)) {
            $likeOperator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('pickup_location', $likeOperator, "%{$search}%")
                    ->orWhere('dropoff_location', $likeOperator, "%{$search}%")
                    ->orWhere('status', $likeOperator, "%{$search}%")
                    ->orWhere('tracking_number', $likeOperator, "%{$search}%")
                    ->orWhere('waybill_number', $likeOperator, "%{$search}%")
                    ->orWhere('receiver_name', $likeOperator, "%{$search}%")
                    ->orWhere('receiver_phone', $likeOperator, "%{$search}%")
                    ->orWhere('sender_name', $likeOperator, "%{$search}%")
                    ->orWhere('sender_phone', $likeOperator, "%{$search}%")
                    ->orWhere('package_type', $likeOperator, "%{$search}%")
                    ->orWhere('package_description', $likeOperator, "%{$search}%");
            });
        }

        return $query->latest()->paginate($dto->perPage);
    }
}
