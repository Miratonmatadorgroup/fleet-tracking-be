<?php

namespace App\Actions\Auth;

use App\DTO\Auth\DeveloperRegisterDTO;
use App\Events\Authentication\OtpRequestedEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterDeveloperAction
{
    public function execute(
        DeveloperRegisterDTO $dto
    ): array {
        if (
            User::where('email', $dto->email)->exists()
        ) {
            throw new \Exception(
                'Email already exists.'
            );
        }

        $otp = (string) random_int(
            100000,
            999999
        );

        $reference = 'pending_developer_' . Str::uuid();
        Cache::put($reference,
            [
                'type' => 'developer_registration',

                'name' => $dto->name,

                'email' => $dto->email,

                'phone' => $dto->phone,

                'password' => Hash::make(
                    $dto->password
                ),

                'company_name' => $dto->company_name,

                'company_website' => $dto->company_website,

                'callback_url' => $dto->callback_url,

                'otp_code' => $otp,

                'otp_expires_at' => now()->addMinutes(10),

            ],

            now()->addMinutes(15)

        );

        event(
            new OtpRequestedEvent(

                'email',

                $dto->email,

                $otp,

                $dto->name

            )
        );

        return [

            'reference' => $reference

        ];
    }
}
