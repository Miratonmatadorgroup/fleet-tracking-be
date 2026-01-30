<?php

namespace App\Actions\Driver;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Services\SmileIdService;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Mail;
use App\Mail\DriverApplicationReceived;
use Illuminate\Support\Facades\Storage;
use App\Enums\DriverApplicationStatusEnums;
use App\DTOs\Driver\ApplyAsDriverByAdminDTO;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Notifications\Admin\NewDriverApplicationNotification;
use App\Notifications\User\DriverApplicationReceivedNotification;

// NORMAL FLOW WITHOUT REAL DRIVER LICESNE VERIFICATION OR NIN
class ApplyAsDriverByAdminAction
{
    public function execute(
        ApplyAsDriverByAdminDTO $dto,
        TwilioService $twilio,
        TermiiService $termii
    ): Driver {
        $user = User::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->firstOrFail();

        if (Driver::where('user_id', $user->id)->exists()) {
            abort(409, 'This user has already submitted a driver application.');
        }

        if ($dto->license_expiry_date) {
            $expiry = Carbon::parse($dto->license_expiry_date);
            if ($expiry->lt(now()->addMonths(6))) {
                abort(422, 'The license expiry date must be at least 6 months from today.');
            }
        }

        $paystack = app(\App\Services\PaystackService::class);

        if ($dto->account_number && $dto->bank_code) {
            $result = $paystack->resolveAccountName($dto->account_number, $dto->bank_code);

            if (! $result['success']) {
                throw new \Exception("Unable to verify bank account: {$result['error']}");
            }

            $accountName = $result['account_name'];

            //Enforce name match with the user's existing name
            if (strcasecmp(trim($user->name), trim($accountName)) !== 0) {
                throw new \Exception(
                    "User name does not match the bank account name. " .
                        "Profile: {$user->name}, Bank: {$accountName}"
                );
            }
        } else {
            $accountName = $dto->account_name;
        }

        $driver = Driver::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'gender' => $dto->gender,
            'email' => $user->email,
            'phone' => $user->phone,
            'whatsapp_number' => $user->whatsapp_number,
            'address' => $dto->address,
            'transport_mode' => $dto->transport_mode,
            'status' => $dto->status ?? DriverStatusEnums::INACTIVE,
            'driver_license_number' => $dto->driver_license_number,
            'license_expiry_date' => $dto->license_expiry_date,
            'license_image_path' => $dto->license_image->store('driver_ids', 'public'),
            'national_id_number' => $dto->national_id_number,
            'national_id_image_path' => $dto->national_id_image?->store('driver_ids', 'public'),
            'profile_photo' => $dto->profile_photo->store('driver_profiles', 'public'),
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'bank_name' => $dto->bank_name,
            'account_name' => $accountName,
            'account_number' => $dto->account_number,
            'date_of_birth' => $dto->date_of_birth,
            'years_of_experience' => $dto->years_of_experience,
            'next_of_kin_name' => $dto->next_of_kin_name,
            'next_of_kin_phone' => $dto->next_of_kin_phone,
            'application_status' => DriverApplicationStatusEnums::REVIEW,
        ]);

        //Notifications

        //In-app Notification for user
        $user->notify(new \App\Notifications\User\DriverApplicationReceivedNotification($driver->name));

        if ($driver->email) {
            try {
                Mail::to($driver->email)->send(new DriverApplicationReceived($driver));
            } catch (\Throwable $e) {
                logError('AdminApplyDriver email failed', $e);
            }
        }

        Notification::send(User::role('admin')->get(), new NewDriverApplicationNotification($driver));

        $msg = "Hi {$driver->name}, your LoopFreight driver application has been received. We’ll review and get back to you shortly.";

        try {
            $termii->sendSms($driver->phone, $msg);
            $twilio->sendWhatsAppMessage($driver->phone, $msg);
        } catch (\Throwable $e) {
            logError("SMS/WhatsApp error", $e);
        }

        return $driver;
    }
}

// class ApplyAsDriverByAdminAction
// {
//     public function execute(
//         ApplyAsDriverByAdminDTO $dto,
//         TwilioService $twilio,
//         TermiiService $termii,
//     ): Driver {
//         $user = User::where('email', $dto->identifier)
//             ->orWhere('phone', $dto->identifier)
//             ->orWhere('whatsapp_number', $dto->identifier)
//             ->firstOrFail();

//         if (Driver::where('user_id', $user->id)->exists()) {
//             abort(409, 'This user has already submitted a driver application.');
//         }

//         $smileId = app(SmileIdService::class);

//         if (!empty($dto->national_id_number)) {
//             $ninResponse = $smileId->submitNin($user, $dto->national_id_number);

//             if (! $ninResponse['success']) {
//                 throw new Exception("NIN verification failed. Response: " . json_encode($ninResponse['raw']));
//             }

//             $ninDetails = $ninResponse['details'];

//             $userName = strtolower(trim($user->name));
//             $ninName = strtolower(trim($ninDetails['full_name']));
//             $userDob = date('Y-m-d', strtotime($user->date_of_birth));
//             $ninDob = $ninDetails['dob'];
//             $userGender = strtolower($user->gender);
//             $ninGender = strtolower($ninDetails['gender']);

//             if (levenshtein($userName, $ninName) > 3) {
//                 throw new Exception("Name on NIN does not match user profile name.");
//             }

//             if ($ninDob && $ninDob !== $userDob) {
//                 throw new Exception("Date of birth on NIN does not match user profile.");
//             }

//             if ($ninGender && $ninGender !== $userGender) {
//                 throw new Exception("Gender on NIN does not match user profile.");
//             }
//         }

//         $licenseResponse = $smileId->verifyDriverLicenseDocument($user, [
//             'driver_license_number' => $dto->driver_license_number,
//             'driver_license_front'  => $dto->license_image,
//             'selfie_image'          => $dto->profile_photo,
//         ]);

//         if (empty($licenseResponse['ResultCode']) || !in_array($licenseResponse['ResultCode'], ['0810', '1012'])) {
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
//             ($verifiedLicenseNo && $verifiedLicenseNo !== strtolower($dto->driver_license_number)) ||
//             ($verifiedDob && $verifiedDob !== date('Y-m-d', strtotime($dto->date_of_birth)))
//         ) {
//             throw new Exception("Driver’s license details do not match provided information.");
//         }

//         $paystack = app(PaystackService::class);

//         if ($dto->account_number && $dto->bank_code) {
//             $result = $paystack->resolveAccountName($dto->account_number, $dto->bank_code);

//             if (! $result['success']) {
//                 throw new Exception("Unable to verify bank account: {$result['error']}");
//             }

//             $accountName = $result['account_name'];

//             if (strcasecmp(trim($user->name), trim($accountName)) !== 0) {
//                 throw new Exception("User name does not match bank account. Profile: {$user->name}, Bank: {$accountName}");
//             }
//         } else {
//             $accountName = $dto->account_name;
//         }

//         $licenseImagePath = $dto->license_image->store('driver_ids', 'public');
//         $profilePhotoPath = $dto->profile_photo->store('driver_profiles', 'public');
//         $nationalIdImagePath = $dto->national_id_image?->store('national_ids', 'public');

//         $driver = Driver::create([
//             'user_id' => $user->id,
//             'name' => $user->name,
//             'gender' => $dto->gender,
//             'email' => $user->email,
//             'phone' => $user->phone,
//             'whatsapp_number' => $user->whatsapp_number,
//             'address' => $dto->address,
//             'transport_mode' => $dto->transport_mode,
//             'status' => $dto->status ?? DriverStatusEnums::INACTIVE,
//             'driver_license_number' => $dto->driver_license_number,
//             'license_expiry_date' => $dto->license_expiry_date,
//             'license_image_path' => $licenseImagePath,
//             'national_id_number' => $dto->national_id_number,
//             'national_id_image_path' => $nationalIdImagePath,
//             'profile_photo' => $profilePhotoPath,
//             'latitude' => $dto->latitude,
//             'longitude' => $dto->longitude,
//             'application_status' => DriverApplicationStatusEnums::REVIEW,
//             'bank_name' => $dto->bank_name,
//             'account_name' => $accountName,
//             'account_number' => $dto->account_number,
//             'date_of_birth' => $dto->date_of_birth,
//             'years_of_experience' => $dto->years_of_experience,
//             'next_of_kin_name' => $dto->next_of_kin_name,
//             'next_of_kin_phone' => $dto->next_of_kin_phone,
//         ]);

//         $user->notify(new DriverApplicationReceivedNotification($driver->name));

//         if ($driver->email) {
//             try {
//                 Mail::to($driver->email)->send(new DriverApplicationReceived($driver));
//             } catch (\Throwable $e) {
//                 logError('AdminApplyDriver email failed', $e);
//             }
//         }

//         Notification::send(User::role('admin')->get(), new NewDriverApplicationNotification($driver));

//         $msg = "Hi {$driver->name}, your LoopFreight driver application has been received. We’ll review and get back to you shortly.";
//         try {
//             $termii->sendSms($driver->phone, $msg);
//             $twilio->sendWhatsAppMessage($driver->phone, $msg);
//         } catch (\Throwable $e) {
//             logError("SMS/WhatsApp error", $e);
//         }

//         return $driver;
//     }
// }
