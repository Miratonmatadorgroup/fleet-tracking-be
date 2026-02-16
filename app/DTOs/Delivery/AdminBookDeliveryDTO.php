<?php

namespace App\DTOs\Delivery;

use App\Models\User;
use Illuminate\Http\Request;
use App\Enums\PackageTypeEnums;
use Illuminate\Validation\Rule;
use App\Enums\DeliveryTypeEnums;
use App\Enums\TransportModeEnums;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminBookDeliveryDTO
{
    public array $data;
    public ?User $customer;

    public static function fromRequest(Request $request): self
    {
        $admin = Auth::user();


        if (!$admin || !$admin->hasAnyRole(['admin', 'customer_care'])) {
            throw ValidationException::withMessages([
                'auth' => ['Only admins or customer care agents can perform this action.']
            ]);
        }


        $rules = [
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
            'package_type' => ['required', Rule::in(array_column(PackageTypeEnums::cases(), 'value'))],
            'other_package_type' => 'nullable|string|max:255',

            // These fields will be required for admin-assisted bookings
            'identifier' => 'nullable|string',
            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|string|max:20',
            'sender_whatsapp_number' => 'nullable|string|max:20',
            'sender_email' => 'nullable|email',
        ];

        $validated = $request->validate($rules);

        if (
            $validated['package_type'] === PackageTypeEnums::OTHERS->value &&
            empty($validated['other_package_type'])
        ) {
            throw ValidationException::withMessages([
                'other_package_type' => ['Other package type is required when package_type is OTHERS'],
            ]);
        }

        $resolvedUser = null;

        // Try to find existing registered user
        if (!empty($validated['identifier'])) {
            $resolvedUser = User::where('email', $validated['identifier'])
                ->orWhere('phone', $validated['identifier'])
                ->orWhere('whatsapp_number', $validated['identifier'])
                ->first();
        }


        return new self(
            collect($validated)->except('identifier')->toArray(),
            $resolvedUser // may be null
        );
    }

    public function __construct(array $data, ?User $customer)
    {
        $this->data = $data;
        $this->customer = $customer;
    }
}
