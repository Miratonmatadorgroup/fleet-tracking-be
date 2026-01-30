<?php

namespace App\DTOs\Driver;

class ApplyAsDriverByAdminDTO
{
    public function __construct(
        public string $identifier,
        public ?string $address,
        public string $gender,
        public string $transport_mode,
        public ?string $status,
        public string $driver_license_number,
        public ?string $license_expiry_date,
        public $license_image,
        public ?string $national_id_number,
        public $national_id_image,
        public $profile_photo,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $bank_name,
        public ?string $bank_code,
        public ?string $account_name,
        public ?string $account_number,
        public ?string $date_of_birth,
        public ?int $years_of_experience,
        public string $next_of_kin_name,
        public string $next_of_kin_phone
    ) {}

    public static function fromRequest($request): self
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'address' => 'nullable|string|max:255',
            'gender' => 'required|string|max:255',
            'transport_mode' => ['required', \Illuminate\Validation\Rule::in(array_column(\App\Enums\TransportModeEnums::cases(), 'value'))],
            'status' => ['sometimes', \Illuminate\Validation\Rule::in(array_column(\App\Enums\DriverStatusEnums::cases(), 'value'))],
            'driver_license_number' => 'required|string|max:100',
            'license_expiry_date' => 'nullable|date',
            'license_image' => 'required|image|max:5120',
            'national_id_number' => 'nullable|string|max:100',
            'national_id_image' => 'nullable|image|max:5120',
            'profile_photo' => 'required|image|max:5120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'bank_name' => 'nullable|string|max:255',
            'bank_code' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'years_of_experience' => 'nullable|numeric|min:0|max:100',
            'next_of_kin_name' => 'required|string|max:255',
            'next_of_kin_phone' => 'required|string|max:255',
        ]);

        return new self(
            identifier: $validated['identifier'],
            address: $validated['address'] ?? null,
            gender: $validated['gender'],
            transport_mode: $validated['transport_mode'],
            status: $validated['status'] ?? null,
            driver_license_number: $validated['driver_license_number'],
            license_expiry_date: $validated['license_expiry_date'] ?? null,
            license_image: $request->file('license_image'),
            national_id_number: $validated['national_id_number'] ?? null,
            national_id_image: $request->file('national_id_image') ?? null,
            profile_photo: $request->file('profile_photo'),
            latitude: $validated['latitude'] ?? null,
            longitude: $validated['longitude'] ?? null,
            bank_name: $validated['bank_name'] ?? null,
             bank_code: $validated['bank_code'] ?? null,
            account_name: $validated['account_name'] ?? null,
            account_number: $validated['account_number'] ?? null,
            date_of_birth: $validated['date_of_birth'] ?? null,
            years_of_experience: $validated['years_of_experience'] ?? null,
            next_of_kin_name: $validated['next_of_kin_name'],
            next_of_kin_phone: $validated['next_of_kin_phone']
        );
    }
}
