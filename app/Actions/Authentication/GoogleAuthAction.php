<?php

namespace App\Actions\Authentication;

use App\Models\User;
use App\Services\UserProvisioningManager;
use App\Services\WalletService;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleAuthAction
{
    public function __construct(
        protected WalletService $walletService,
        protected UserProvisioningManager $provisioningManager
    ) {}

    /**
     * Execute Google login/signup.
     *
     * @param  \Laravel\Socialite\Contracts\User  $googleUser
     * @return array
     */
    public function execute($googleUser, bool $isDev = false): array
    {
        //Check if user exists by provider
        $user = User::where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        $isNewUser = false;

        if (!$user) {

            //Ensure no existing email/password account
            $existingEmailUser = User::where('email', $googleUser->getEmail())->first();

            if ($existingEmailUser) {

                // Attach Google to existing account
                $existingEmailUser->update([
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                ]);

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

            //Create new user
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Unknown',
                'email' => $googleUser->getEmail(),
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(16)), // random password
                'registration_type' => $isDev ? 'developer' : 'user',
            ]);

            if ($isDev) {
                $user->assignRole('dev');
            }

            $isNewUser = true;

            //Provision wallet and other onboarding
            $wallet = $this->walletService->createForUser(
                $user->id,
                'NGN',
                true,
                'shanono'
            );

            // Optionally run any additional provisioning
            $this->provisioningManager->provision($user);
        } else {
            // Existing Google user — get wallet
            $wallet = $user->wallet;
        }

        return [
            'user' => $user,
            'wallet' => $wallet,
            'is_new_user' => $isNewUser,
        ];
    }
}
