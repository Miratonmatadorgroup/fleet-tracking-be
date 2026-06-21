<?php

namespace App\Actions\Authentication;

use App\Enums\UserTypesEnums;
use App\Models\User;
use App\Services\UserProvisioningManager;
use App\Services\WalletService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleTokenAuthAction
{
    public function __construct(
        protected WalletService $walletService,
        protected UserProvisioningManager $provisioningManager
    ) {}

    public function execute($googleUser, bool $isDev = false): array
    {
        $user = User::where('provider', 'google')
            ->where('provider_id', $googleUser->id)
            ->first();

        $isNewUser = false;

        if (!$user) {

            $existingEmailUser = User::where('email', $googleUser->email)->first();

            if ($existingEmailUser) {

                // Attach Google to existing account
                $existingEmailUser->update([
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'user_type' => UserTypesEnums::INDIVIDUAL_OPERATOR,
                ]);

                //Upgrade to developer if requested
                if ($isDev && !$existingEmailUser->hasRole('dev')) {
                    $existingEmailUser->assignRole('dev');
                    $existingEmailUser->update([
                        'registration_type' => 'developer'
                    ]);
                }

                return [
                    'user' => $existingEmailUser,
                    'wallet' => $existingEmailUser->wallet,
                    'is_new_user' => false,
                ];
            }

            $user = User::create([
                'name' => $googleUser->name ?? 'Unknown',
                'email' => $googleUser->email,
                'provider' => 'google',
                'provider_id' => $googleUser->id,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(16)),
                'registration_type' => $isDev ? 'developer' : 'user',
                'user_type' => UserTypesEnums::INDIVIDUAL_OPERATOR,

            ]);

            if ($isDev) {
                $user->assignRole('dev');
            }

            $isNewUser = true;

            $wallet = $this->walletService->createForUser(
                $user->id,
                'NGN',
                true,
                'shanono'
            );

        } else {

            $wallet = $user->wallet;
        }

        return [
            'user' => $user,
            'wallet' => $wallet,
            'is_new_user' => $isNewUser,
        ];
    }
}
