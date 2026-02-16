<?php

namespace App\DTOs\Delivery;

use Illuminate\Http\Request;
use App\Enums\PackageTypeEnums;
use Illuminate\Validation\Rule;
use App\Enums\DeliveryTypeEnums;
use App\Enums\TransportModeEnums;

class UpdateDeliveryDTO
{
    public array $data;
    public ?float $amount;

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'pickup_location' => 'sometimes|required|string',
            'dropoff_location' => 'sometimes|required|string',
            'mode_of_transportation' => ['sometimes', 'required', Rule::in(array_column(TransportModeEnums::cases(), 'value'))],
            'delivery_type' => ['sometimes', 'required', Rule::in(array_column(DeliveryTypeEnums::cases(), 'value'))],
            'package_description' => 'sometimes|required|string',
            'package_weight' => 'sometimes|required|numeric|min:0.1',
            'delivery_pics' => 'nullable',
            'delivery_pics.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'delivery_pics_remove' => 'nullable|array',
            'delivery_pics_remove.*' => 'string',
            'delivery_date' => 'sometimes|required|date',
            'delivery_time' => 'sometimes|required|date_format:H:i',
            'receiver_name' => 'sometimes|required|string|max:255',
            'receiver_phone' => 'sometimes|required|string|max:20',
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:20',
            'package_type' => ['sometimes', 'required', Rule::in(array_column(PackageTypeEnums::cases(), 'value'))],
            'other_package_type' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0.1'
        ]);

        return new self(
            collect($validated)->except('tracking_number', 'amount')->toArray(),
            $validated['amount'] ?? null
        );
    }

    public function __construct(array $data, ?float $amount)
    {
        $this->data = $data;
        $this->amount = $amount;
    }
}
