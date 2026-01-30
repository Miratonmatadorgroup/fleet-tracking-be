<?php

namespace App\DTOs\Delivery;

use Illuminate\Http\Request;
use App\Enums\PackageTypeEnums;
use Illuminate\Validation\Rule;
use App\Enums\DeliveryTypeEnums;
use App\Enums\TransportModeEnums;
use Illuminate\Support\Facades\Validator;

class BookDeliveryDTO
{
    public array $validated;

    public static function fromRequest(Request $request, bool $isAdmin): self
    {
        $baseRules = [
            'pickup_location' => 'required|string',
            'dropoff_location' => 'required|string',
            'mode_of_transportation' => ['required', Rule::in(array_column(TransportModeEnums::cases(), 'value'))],
            'delivery_type' => ['required', Rule::in(array_column(DeliveryTypeEnums::cases(), 'value'))],
            'package_description' => 'required|string',
            'package_weight' => 'required|numeric|min:0.1',
            'delivery_pics' => 'nullable',
            'delivery_pics.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'delivery_date' => 'required|date',
            'delivery_time' => 'required|date_format:H:i',
            'receiver_name' => 'required|string|max:255',
            'receiver_phone' => 'required|string|max:20',
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:20',
            'package_type' => ['required', Rule::in(array_column(PackageTypeEnums::cases(), 'value'))],
            'other_package_type' => 'nullable|string|max:255',
        ];

        if ($isAdmin) {
            $baseRules['sender_name'] = 'required|string|max:255';
            $baseRules['sender_phone'] = 'required|string|max:20';
        }

        $validated = Validator::make($request->all(), $baseRules)->validate();

        if ($validated['package_type'] === PackageTypeEnums::OTHERS->value) {
            Validator::make($request->all(), [
                'other_package_type' => 'required|string|max:255',
            ])->validate();
        }

        $dto = new self();
        $dto->validated = $validated;

        return $dto;
    }
}
