<?php

namespace App\Actions\Authentication;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\DTOs\Authentication\VerifyOtpDTO;
use App\Services\UserProvisioningManager;

class VerifyOtpAction
{
    public function __construct(
        protected WalletService $walletService,
        protected UserProvisioningManager $provisioningManager
    ) {}

    public function execute(VerifyOtpDTO $dto): array
    {
        $cacheKey = $dto->reference;
        $pending = Cache::get($cacheKey);

        if (!$pending) {
            throw new \Exception("No pending request found", 404);
        }

        if ((string) $pending['otp_code'] !== (string) $dto->otp) {
            throw new \Exception("Invalid OTP", 422);
        }

        if (now()->greaterThan($pending['otp_expires_at'])) {
            throw new \Exception("OTP expired", 422);
        }

        $type = $pending['type'] ?? null;

        if ($type === 'update') {
            $user = User::findOrFail($pending['user_id']);

            $field = $pending['channel'] ?? null;
            $newValue = $pending['identifier'] ?? null;

            if ($field && $newValue) {
                $user->{$field} = $newValue;

                $verifyColumn = match ($field) {
                    'email'           => 'email_verified_at',
                    'phone'           => 'phone_verified_at',
                    'whatsapp_number' => 'whatsapp_number_verified_at',
                    default           => null,
                };

                if ($verifyColumn) {
                    $user->{$verifyColumn} = now();
                }

                $user->save();
            }

            Cache::forget($cacheKey);

            return [
                'user'   => $user,
                'wallet' => null,
            ];
        }


        if ($type === 'registration') {
            $result = DB::transaction(function () use ($pending) {
                $isNewUser = false;
                $identifier = $pending['identifier'] ?? null;
                $channel = $pending['channel'] ?? null;
                $isDev = (bool) ($pending['is_dev'] ?? false);

                $user = null;

                if ($identifier && $channel === 'email') {
                    $user = User::where('email', $identifier)
                        ->whereNull('email_verified_at')
                        ->first();
                } elseif ($identifier && $channel === 'phone') {
                    $user = User::where('phone', $identifier)
                        ->whereNull('phone_verified_at')
                        ->first();
                } elseif ($identifier && $channel === 'whatsapp_number') {
                    $user = User::where('whatsapp_number', $identifier)
                        ->whereNull('whatsapp_number_verified_at')
                        ->first();
                }

                if (!$user) {

                    $isNewUser = true;
                    $user = User::create([
                        'name'            => $pending['name'] ?? null,
                        'email'           => $channel === 'email' ? $identifier : null,
                        'phone'           => $channel === 'phone' ? $identifier : null,
                        'whatsapp_number' => $channel === 'whatsapp_number' ? $identifier : null,
                        'password'        => $pending['password'] ?? null,
                    ]);
                }

                $verifyColumn = match ($channel) {
                    'email'    => 'email_verified_at',
                    'phone'    => 'phone_verified_at',
                    'whatsapp_number' => 'whatsapp_number_verified_at',
                    default    => null,
                };

                if ($verifyColumn) {
                    $user->{$verifyColumn} = now();
                    $user->save();
                }

                /**
                 *  ROLE ASSIGNMENT
                 */
                // if ($isNewUser) {
                //     $user->assignRole('dev');
                // }

                $wallet = null;

                if ($isDev) {

                    if (!$user->hasRole('dev')) {
                        $user->assignRole('dev');
                    }

                    // Ensure wallet exists
                    if (!$user->wallet) {
                        $this->walletService->createForUser(
                            $user->id,
                            $pending['currency'] ?? 'NGN',
                            true,
                            $pending['provider'] ?? 'shanono'
                        );

                        $user->refresh();
                    }

                    // Provision sandbox API
                    $this->provisioningManager->provision($user);

                    $wallet = $user->wallet;
                } else {

                    $wallet = $this->walletService->createForUser(
                        $user->id,
                        $pending['currency'] ?? 'NGN',
                        true,
                        $pending['provider'] ?? 'default'
                    );
                }
                return compact('user', 'wallet');
            });

            Cache::forget($cacheKey);
            return $result;
        }

        throw new \Exception("Unknown OTP type", 400);
    }
}
