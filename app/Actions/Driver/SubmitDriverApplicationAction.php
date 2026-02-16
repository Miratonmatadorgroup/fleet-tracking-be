<?php

namespace App\Actions\Driver;

use Exception;
use App\Models\User;
use App\Models\Driver;
use App\Services\NubapiService;
use App\Enums\DriverStatusEnums;
use App\Services\SmileIdService;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Storage;
use App\DTOs\Driver\DriverApplicationDTO;
use App\Enums\DriverApplicationStatusEnums;

// NORMAL FLOW WITHOUT REAL DRIVER LICESNE VERIFICATION OR NIN
class SubmitDriverApplicationAction
{
    public function execute(DriverApplicationDTO $dto, User $user): Driver
    {
        $data = $dto->data;
        $resolver = app(\App\Services\BankAccountNameResolver::class);

        $result = $resolver->resolve(
            $data['account_number'],
            $data['bank_code']
        );

        if (! $result['success'] || empty($result['account_name'])) {
            throw new Exception(
                "Unable to verify bank account. " . ($result['error'] ?? '')
            );
        }

        $accountName = $result['account_name'];


        if (! $this->namesLooselyMatch($user->name, $accountName)) {
            throw new Exception(
                "Profile name does not reasonably match bank account name. " .
                    "Profile: {$user->name}, Bank: {$accountName}"
            );
        }



        $licenseImagePath = $data['license_image']->store('driver_ids', 'public');
        $profilePhotoPath = $data['profile_photo']->store('driver_profiles', 'public');

        $nationalIdImagePath = null;

        if (!empty($data['national_id_image'])) {
            $nationalIdImagePath = $data['national_id_image']->store('national_ids', 'public');
        }


        $driver = Driver::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'gender' => $data['gender'],
            'email' => $user->email,
            'phone' => $user->phone,
            'whatsapp_number' => $user->whatsapp_number,
            'address' => $data['address'] ?? null,
            'transport_mode' => $data['transport_mode'],
            'status' => $data['status'] ?? DriverStatusEnums::INACTIVE,
            'driver_license_number' => $data['driver_license_number'],
            'license_expiry_date' => $data['license_expiry_date'] ?? null,
            'license_image_path' => $licenseImagePath,
            'national_id_number' => $data['national_id_number'] ?? null,
            'national_id_image_path' => $nationalIdImagePath,
            'profile_photo' => $profilePhotoPath,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'application_status' => DriverApplicationStatusEnums::REVIEW,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_code' => $data['bank_code'],
            'account_name' => $accountName,
            'account_number' => $data['account_number'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'years_of_experience' => $data['years_of_experience'] ?? null,
            'next_of_kin_name' => $data['next_of_kin_name'],
            'next_of_kin_phone' => $data['next_of_kin_phone'],
        ]);


        return $driver;
    }

    private function namesLooselyMatch(string $profileName, string $bankName): bool
    {
        $normalize = function ($name) {
            $name = strtoupper($name);

            // Remove multiple spaces
            $name = preg_replace('/\s+/', ' ', $name);

            // Remove special characters
            $name = preg_replace('/[^A-Z\s]/', '', $name);

            return trim($name);
        };

        $profile = $normalize($profileName);
        $bank    = $normalize($bankName);

        $profileParts = explode(' ', $profile);
        $bankParts    = explode(' ', $bank);

        // Must match at least FIRST + LAST name
        $matches = array_intersect($profileParts, $bankParts);

        return count($matches) >= 2;
    }
}

// class SubmitDriverApplicationAction
// {
//     public function execute(DriverApplicationDTO $dto, User $user): Driver
//     {
//         $data = $dto->data;
//         $smileId = app(SmileIdService::class);

//         $nin = $data['national_id_number'];
//         $ninResponse = $smileId->submitNin($user, $nin);

//         if (! $ninResponse['success']) {
//             throw new Exception("NIN verification failed. Response: " . json_encode($ninResponse['raw']));
//         }

//         $ninDetails = $ninResponse['details'];

//         //Normalize and compare user data with NIN details
//         $userName = strtolower(trim($user->name));
//         $ninName = strtolower(trim($ninDetails['full_name']));
//         $userDob = date('Y-m-d', strtotime($user->date_of_birth));
//         $ninDob = $ninDetails['dob'];
//         $userGender = strtolower($user->gender);
//         $ninGender = strtolower($ninDetails['gender']);

//         // Levenshtein allows small typos (distance threshold <=3)
//         if (levenshtein($userName, $ninName) > 3) {
//             throw new Exception("Name on NIN does not match your profile name.");
//         }

//         if ($ninDob && $ninDob !== $userDob) {
//             throw new Exception("Date of birth on NIN does not match your profile.");
//         }

//         if ($ninGender && $ninGender !== $userGender) {
//             throw new Exception("Gender on NIN does not match your profile.");
//         }

//         // DRIVERS LICENSE VERIFICATION
//         $licenseResponse = $smileId->verifyDriverLicenseDocument($user, [
//             'driver_license_number' => $data['driver_license_number'],
//             'driver_license_front'  => $data['license_image'],
//             'selfie_image'          => $data['profile_photo'],
//         ]);

//         if (empty($licenseResponse['ResultCode']) || $licenseResponse['ResultCode'] !== '0810') {
//             throw new Exception("Driver license verification failed. Response: " . json_encode($licenseResponse));
//         }

//         $verified = $licenseResponse['Result'] ?? [];
//         $verifiedName = trim(strtolower($verified['FullName'] ?? ''));
//         $verifiedLicenseNo = trim(strtolower($verified['IDNumber'] ?? ''));
//         $verifiedDob = isset($verified['DateOfBirth']) ? date('Y-m-d', strtotime($verified['DateOfBirth'])) : null;
//         $verifiedExpiryDate = isset($verified['ExpiryDate']) ? date('Y-m-d', strtotime($verified['ExpiryDate'])) : null;

//         if ($verifiedExpiryDate && now()->diffInMonths($verifiedExpiryDate, false) < 6) {
//             throw new Exception("License must be valid for at least 6 more months.");
//         }

//         if (
//             ($verifiedName && levenshtein($verifiedName, strtolower($user->name)) > 3) ||
//             ($verifiedLicenseNo && $verifiedLicenseNo !== strtolower($data['driver_license_number'])) ||
//             ($verifiedDob && $verifiedDob !== date('Y-m-d', strtotime($data['date_of_birth'])))
//         ) {
//             throw new Exception("Driver’s license details do not match your application information.");
//         }

//         // BANK DETAILS VERIFICATION
//         $paystack = app(PaystackService::class);
//         $bankResult = $paystack->resolveAccountName($data['account_number'], $data['bank_code']);

//         if (! $bankResult['success']) {
//             throw new Exception("Unable to verify bank account: {$bankResult['error']}");
//         }

//         $accountName = $bankResult['account_name'];
//         if (strcasecmp(trim($user->name), trim($accountName)) !== 0) {
//             throw new Exception("Profile name mismatch with bank account. Profile: {$user->name}, Bank: {$accountName}");
//         }

//         //SAVE FILES AND CREATE DRIVER TABLE
//         $licenseImagePath = $data['license_image']->store('driver_ids', 'public');
//         $profilePhotoPath = $data['profile_photo']->store('driver_profiles', 'public');
//         $nationalIdImagePath = $data['national_id_image']?->store('national_ids', 'public');

//         return Driver::create([
//             'user_id' => $user->id,
//             'name' => $user->name,
//             'gender' => $data['gender'],
//             'email' => $user->email,
//             'phone' => $user->phone,
//             'whatsapp_number' => $user->whatsapp_number,
//             'address' => $data['address'] ?? null,
//             'transport_mode' => $data['transport_mode'],
//             'status' => $data['status'] ?? DriverStatusEnums::INACTIVE,
//             'driver_license_number' => $data['driver_license_number'],
//             'license_expiry_date' => $data['license_expiry_date'] ?? null,
//             'license_image_path' => $licenseImagePath,
//             'national_id_number' => $data['national_id_number'] ?? null,
//             'national_id_image_path' => $nationalIdImagePath,
//             'profile_photo' => $profilePhotoPath,
//             'latitude' => $data['latitude'] ?? null,
//             'longitude' => $data['longitude'] ?? null,
//             'application_status' => DriverApplicationStatusEnums::REVIEW,
//             'bank_name' => $data['bank_name'] ?? null,
//             'account_name' => $accountName,
//             'account_number' => $data['account_number'] ?? null,
//             'date_of_birth' => $data['date_of_birth'] ?? null,
//             'years_of_experience' => $data['years_of_experience'] ?? null,
//             'next_of_kin_name' => $data['next_of_kin_name'],
//             'next_of_kin_phone' => $data['next_of_kin_phone'],
//         ]);
//     }
// }
