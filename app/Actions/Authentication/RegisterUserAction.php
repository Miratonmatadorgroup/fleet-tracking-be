<?php

namespace App\Actions\Authentication;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\DTOs\Authentication\RegisterUserDTO;
use App\Events\Authentication\OtpRequestedEvent;


class RegisterUserAction
{
    public function execute(RegisterUserDTO $dto): array
    {
        if (User::where('email', $dto->email)->exists()) {
            throw new \Exception('Email already in use', 422);
        }

        $otp = (string) random_int(100000, 999999);
        $reference = 'pending_registration_' . Str::uuid();

        Cache::put($reference, [
            'type'          => 'registration',
            'name'          => $dto->name,
            'email'         => $dto->email,
            'password'      => Hash::make($dto->password),
            'dob'           => $dto->dob,
            'gender'        => $dto->gender,
            'user_type'     => $dto->user_type,
            'business_type' => $dto->business_type,
            'cac_number'    => $dto->cac_number,
            'nin_number'    => $dto->nin_number,
            'cac_document'  => $dto->cac_document,
            'kyb_verified'  => $dto->kyb_verified,
            'nin_verification' => [
                'status'     => $dto->nin_verification_status ?? 'pending',
                'confidence' => $dto->nin_match_confidence ?? null,
            ],
            'otp_code'      => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ], now()->addMinutes(15));

        event(new OtpRequestedEvent(
            'email',
            $dto->email,
            $otp,
            $dto->name
        ));

        return [
            'reference' => $reference,
        ];
    }
}
