<?php

namespace App\Actions\Authentication;

use App\Models\User;
use App\Models\Merchant;
use Illuminate\Support\Carbon;
use App\Services\WalletService;
use App\Enums\MerchantStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        if (! $pending) {
            throw ValidationException::withMessages([
                'otp' => 'OTP has already been used or has expired. Please request a new one.',
            ]);
        }


        //Check expiration first

        if (now()->greaterThan(Carbon::parse($pending['otp_expires_at']))) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'otp' => 'OTP has expired',
            ]);
        }

        //Check attempt limit
        if (($pending['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'otp' => 'Too many failed attempts. Please request a new code.',
            ]);
        }

        //Validate OTP
        if ((string) $pending['otp_code'] !== (string) $dto->otp) {
            $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;

            Cache::put($cacheKey, $pending, now()->addMinutes(15));

            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP',
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

            //Validate pending registration state FIRST
            if (
                $pending['user_type'] === 'business_operator'
                && empty($pending['cac_number'])
            ) {
                throw new \Exception('Invalid business registration state');
            }

            if (
                $pending['user_type'] === 'business_operator' &&
                empty($pending['kyb_verified'])
            ) {
                throw ValidationException::withMessages([
                    'verification' => 'Business verification not completed',
                ]);
            }

            $result = DB::transaction(function () use ($pending) {
                // Normalize email for DB search
                $email = strtolower(trim($pending['email']));

                //Create or confirm user
                $user = User::whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->first();

                if ($user) {
                    // Always update email_verified_at if not already set

                    if (! $user->email_verified_at) {
                        $user->email_verified_at = now();
                        $user->save();
                    }
                } else {
                    // Create new user with email_verified_at set
                    $user = User::create([
                        'name'          => $pending['name'],
                        'email'         => $pending['email'],
                        'password'      => $pending['password'],
                        'user_type'     => $pending['user_type'],
                        'dob'           => $pending['dob'],
                        'gender'        => $pending['gender'],
                        'business_type' => $pending['business_type'] ?? null,
                        'cac_number'    => $pending['cac_number'] ?? null,
                        'cac_document'  => $pending['cac_document'] ?? null,
                        'nin_number'    => $pending['nin_number'] ?? null,
                        'email_verified_at' => now(),
                        'nin_verified_at' => now()

                    ]);
                }

                //Create wallet (ALL users)
                $wallet = $this->walletService->getOrCreateForUser(
                    $user->id,
                    'NGN',
                    true,
                    'default'
                );

                $merchant = null;

                //BUSINESS OPERATOR ONLY
                if ($pending['user_type'] === 'business_operator') {

                    // Assign office_admin role
                    if (! $user->hasRole('office_admin')) {
                        $user->assignRole('office_admin');
                    }

                    // Create merchant profile
                    $merchant = Merchant::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'merchant_code' => $this->generateMerchantCode(),
                            'status'        => MerchantStatusEnums::PENDING,
                        ]
                    );
                }

                return compact('user', 'wallet', 'merchant');
            });

            return $result;
        }

        throw new \Exception("Unknown OTP type", 400);
    }

    private function generateMerchantCode(): string
    {
        do {
            $code = (string) random_int(1000000, 9999999);
        } while (Merchant::where('merchant_code', $code)->exists());

        return $code;
    }
}
