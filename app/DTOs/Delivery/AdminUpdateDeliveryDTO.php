<?php
namespace App\DTOs\Delivery;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\PackageTypeEnums;
use App\Enums\DeliveryTypeEnums;
use App\Enums\TransportModeEnums;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminUpdateDeliveryDTO
{
    public array $data;
    public User $customer;

    public static function fromRequest(Request $request): self
    {
        $authUser = Auth::user();
        $isPrivilegedUser = $authUser->hasRole(['admin', 'customer_care']);

        $rules = [
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
            'amount' => 'nullable|numeric|min:0.1',
        ];

        if ($isPrivilegedUser) {
            $rules['identifier'] = 'required|string';
        }

        $validated = $request->validate($rules);

        if (
            ($validated['package_type'] ?? null) === PackageTypeEnums::OTHERS->value &&
            empty($validated['other_package_type'])
        ) {
            throw ValidationException::withMessages([
                'other_package_type' => ['Other package type is required when package_type is OTHERS'],
            ]);
        }

        $resolvedUser = $authUser;
        if ($isPrivilegedUser) {
            $identifier = $validated['identifier'];
            $resolvedUser = User::where('email', $identifier)
                ->orWhere('phone', $identifier)
                ->orWhere('whatsapp_number', $identifier)
                ->first();

            if (!$resolvedUser) {
                throw ValidationException::withMessages([
                    'identifier' => ['No user found with the given identifier.'],
                ]);
            }
        }

        return new self(
            collect($validated)->except('identifier')->toArray(),
            $resolvedUser
        );
    }

    public function __construct(array $data, User $customer)
    {
        $this->data = $data;
        $this->customer = $customer;
    }
}
