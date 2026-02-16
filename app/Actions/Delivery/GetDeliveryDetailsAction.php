<?php
namespace App\Actions\Delivery;


use App\Models\Delivery;
use App\DTOs\Delivery\GetDeliveryDetailsDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetDeliveryDetailsAction
{
    public function execute(GetDeliveryDetailsDTO $dto): Delivery
    {
        $delivery = Delivery::with(['customer', 'payment']) 
            ->find($dto->deliveryId);

        if (!$delivery) {
            throw new ModelNotFoundException("Delivery not found.");
        }

        return $delivery;
    }
}
