<?php
namespace App\Actions\Delivery;


use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use App\DTOs\Delivery\CancelDeliveryDTO;
use App\Events\Delivery\DeliveryCancelledEvent;

class CancelDeliveryAction
{
    public function execute(CancelDeliveryDTO $dto): bool
    {
        return DB::transaction(function () use ($dto) {
            $delivery = Delivery::where('uuid', $dto->deliveryId)->firstOrFail();

            $delivery->delete();

            event(new DeliveryCancelledEvent($dto->deliveryId));

            return true;
        });
    }
}
