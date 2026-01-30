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

        $otp = (string) rand(100000, 999999);
        $reference = 'pending_registration_' . Str::uuid();

        Cache::put($reference, [
            'type'          => 'registration',
            'name'          => $dto->name,
            'email'         => $dto->email,
            'password'      => Hash::make($dto->password),

            'user_type'     => $dto->user_type,
            'business_type' => $dto->business_type,
            'cac_number'    => $dto->cac_number,
            'cac_document'  => $dto->cac_document,
            'nin_number'    => $dto->nin_number,

            'otp_code'      => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ], now()->addMinutes(10));

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
