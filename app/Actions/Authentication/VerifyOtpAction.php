<?php

namespace App\Actions\Authentication;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\DTOs\Authentication\VerifyOtpDTO;
use App\Services\UserProvisioningManager;
use Illuminate\Validation\ValidationException;


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

        if (
            (string) $pending['otp_code'] !== (string) $dto->otp ||
            now()->greaterThan($pending['otp_expires_at'])
        ) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP',
            ]);
        }

        // OTP is now confirmed — prevent reuse immediately
        Cache::forget($cacheKey);

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
            return [
                'user'   => $user,
                'wallet' => null,
            ];
        }


        if ($type === 'registration') {
            $result = DB::transaction(function () use ($pending) {

                $user = User::where('email', $pending['email'])
                    ->whereNull('email_verified_at')
                    ->first();

                if (! $user) {
                    $user = User::create([
                        'name'          => $pending['name'],
                        'email'         => $pending['email'],
                        'password'      => $pending['password'],

                        'user_type'     => $pending['user_type'],
                        'business_type' => $pending['business_type'],
                        'cac_number'    => $pending['cac_number'],
                        'cac_document'  => $pending['cac_document'],
                        'nin_number'    => $pending['nin_number'],

                        'email_verified_at' => now(),
                    ]);
                } else {
                    $user->email_verified_at = now();
                    $user->save();
                }

                $wallet = $this->walletService->getOrCreateForUser(
                    $user->id,
                    'NGN',
                    true,
                    'default'
                );


                return compact('user', 'wallet');
            });

            return $result;
        }


        throw new \Exception("Unknown OTP type", 400);
    }
}
