<?php
namespace App\DTOs\Delivery;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GetDeliveryDetailsDTO
{
    public string $deliveryId;

    public static function fromRequest(Request $request): self
    {
        $validated = Validator::make($request->all(), [
            'delivery_id' => 'required|uuid|exists:deliveries,id',
        ])->validate();

        $dto = new self();
        $dto->deliveryId = $validated['delivery_id'];

        return $dto;
    }
}
