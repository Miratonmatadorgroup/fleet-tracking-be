<?php

namespace App\DTOs\Driver;


use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Enums\TransportModeEnums;
use App\Enums\DriverStatusEnums;

class DriverApplicationDTO
{
    public array $data;

    public static function fromRequest(Request $request): self
    {
        $rules = [
            'address' => 'nullable|string|max:255',
            'gender' => 'required|string|max:255',

            'transport_mode' => ['required', Rule::in(array_column(TransportModeEnums::cases(), 'value'))],
            'status' => ['sometimes', Rule::in(array_column(DriverStatusEnums::cases(), 'value'))],

            'driver_license_number' => 'required|string|max:100',
            'license_expiry_date' => 'required|date',
            'license_image' => 'required|image|max:5120',

            'national_id_number' => 'nullable|string|max:100',
            'national_id_image' => 'nullable|image|max:5120',

            'profile_photo' => 'required|image|max:5120',

            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:20',
            'account_number' => 'required|string|max:20',
            'date_of_birth' => 'required|date',
            'years_of_experience' => 'required|numeric|min:0|max:100',
            'next_of_kin_name' => 'required|string|max:255',
            'next_of_kin_phone' => 'required|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);

        if (!empty($request->license_expiry_date)) {
            $expiryDate = now()->parse($request->license_expiry_date);
            if ($expiryDate->lt(now()->addMonths(6))) {
                throw ValidationException::withMessages([
                    'license_expiry_date' => ['License expiry must be at least 6 months from today.']
                ]);
            }
        }

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self($validator->validated());
    }

    public function __construct(array $data)
    {
        $this->data = $data;
    }
}
