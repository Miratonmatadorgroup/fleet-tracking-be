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
        $otp = (string) rand(100000, 999999);

        if ($dto->email) {
            $channel = 'email';
            $identifier = $dto->email;
        } elseif ($dto->phone) {
            $channel = 'phone';
            $identifier = $dto->phone;
        } else {
            $channel = 'whatsapp_number';
            $identifier = $dto->whatsapp_number;
        }

        $column = match ($channel) {
            'email'           => 'email',
            'phone'           => 'phone',
            'whatsapp_number' => 'whatsapp_number',
        };

        $existingUser = User::where($column, $identifier)->first();

        if ($existingUser) {
            $verifiedColumn = match ($channel) {
                'email'           => 'email_verified_at',
                'phone'           => 'phone_verified_at',
                'whatsapp_number' => 'whatsapp_number_verified_at',
            };

            if ($existingUser->{$verifiedColumn}) {
                throw new \Exception(
                    ucfirst(str_replace('_', ' ', $channel)) . ' is already in use by another account.',
                    422
                );
            }
        }

        $reference = "pending_registration_" . Str::uuid();

        Cache::put($reference, [
            'type'            => 'registration',
            'name'            => $dto->name,
            'identifier'      => $identifier,
            'channel'         => $channel,
            'password'        => Hash::make($dto->password),
            'otp_code'        => $otp,
            'is_dev'          => $dto->is_dev,
            'otp_expires_at'  => now()->addMinutes(10),
        ], now()->addMinutes(10));

        Log::info('OTP cached successfully', [
            'reference'  => $reference,
            'expires_at' => now()->addMinutes(10),
        ]);


        event(new OtpRequestedEvent($channel, $identifier, $otp, $dto->name));

        return [
            'reference' => $reference,
            'message'   => "Verification code sent via {$channel}.",
        ];
    }
}
