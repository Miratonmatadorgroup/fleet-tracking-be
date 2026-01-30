<?php

namespace App\Actions\Delivery;

use App\Models\DeliveryAssignmentLog;
use App\DTOs\Delivery\DeliveryAssignmentLogsDTO;

class FetchDeliveryAssignmentLogsAction
{
    public function execute(DeliveryAssignmentLogsDTO $dto)
    {
        $query = DeliveryAssignmentLog::with(['delivery', 'driver.user'])
            ->orderByDesc('assigned_at');

        if ($dto->status) {
            $query->where('status', $dto->status);
        }

        if ($dto->deliveryId) {
            $query->where('delivery_id', $dto->deliveryId);
        }

        return $query->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
