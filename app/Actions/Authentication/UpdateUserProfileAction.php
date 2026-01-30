<?php

namespace App\Actions\Authentication;

use Exception;
use App\Models\User;
use Illuminate\Support\Str;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Services\BankAccountNameResolver;
use App\DTOs\Authentication\UpdateProfileDTO;
use App\Events\Authentication\OtpRequestedEvent;


class UpdateUserProfileAction
{
    public static function execute(UpdateProfileDTO $dto, User $user): array
    {
        $validated = $dto->validated;

        if (isset($validated['name']) && $validated['name'] !== $user->name) {
            $hasBankDetails =
                !empty($user->account_number) ||
                DB::table('drivers')->where('user_id', $user->id)->whereNotNull('account_number')->exists() ||
                DB::table('investors')->where('user_id', $user->id)->whereNotNull('account_number')->exists() ||
                DB::table('partners')->where('user_id', $user->id)->whereNotNull('account_number')->exists();

            if ($hasBankDetails) {
                throw new Exception('You cannot change your name once banking details are set.', 403);
            }

            $user->name = $validated['name'];
            unset($validated['name']);
        }

        if ($user->email_verified_at && isset($validated['email']) && $validated['email'] !== $user->email) {
            throw new Exception('You cannot change a verified email', 403);
        }
        if ($user->phone_verified_at && isset($validated['phone']) && $validated['phone'] !== $user->phone) {
            throw new Exception('You cannot change a verified phone number', 403);
        }
        if ($user->whatsapp_number_verified_at && isset($validated['whatsapp_number']) && $validated['whatsapp_number'] !== $user->whatsapp_number) {
            throw new Exception('You cannot change a verified WhatsApp number', 403);
        }

        foreach (['email', 'phone', 'whatsapp_number'] as $field) {
            if (isset($validated[$field]) && $validated[$field] !== $user->{$field}) {
                $newIdentifier = trim($validated[$field]);

                $otp = (string) rand(100000, 999999);
                $reference = "pending_update_" . Str::uuid();

                Cache::put($reference, [
                    'type'           => 'update',
                    'user_id'        => $user->id,
                    'channel'        => $field,
                    'identifier'     => $newIdentifier,
                    'name'           => $user->name,
                    'otp_code'       => $otp,
                    'is_dev'          => true,
                    'otp_expires_at' => now()->addMinutes(10),
                ], now()->addMinutes(10));

                event(new OtpRequestedEvent($field, $newIdentifier, $otp, $user->name));

                unset($validated[$field]);

                return [
                    'message'   => "Verification code sent to {$field}. Please verify to complete update.",
                    'reference' => $reference,
                ];
            }
        }

        if ($dto->imageFile) {
            if ($user->image && Storage::exists(str_replace('storage/', 'public/', $user->image))) {
                Storage::delete(str_replace('storage/', 'public/', $user->image));
            }

            $filename = uniqid() . '.' . $dto->imageFile->getClientOriginalExtension();
            $path = $dto->imageFile->storeAs('profile_photos', $filename, 'public');
            $user->image = $path;
        }

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        if (!empty($validated['account_number']) && !empty($validated['bank_code'])) {

            if (
                $user->bank_details_updated_at &&
                $user->bank_details_updated_at->diffInHours(now()) < 24
            ) {
                throw new Exception(
                    'You can only update your bank details once every 24 hours.',
                    403
                );
            }

            $resolver = app(BankAccountNameResolver::class);

            $result = $resolver->resolve(
                $validated['account_number'],
                $validated['bank_code']
            );

            if (!$result['success'] || empty($result['account_name'])) {
                throw new Exception(
                    "Unable to verify bank account: {$result['error']}"
                );
            }

            $accountName = $result['account_name'];

            if (!self::namesLooselyMatch($user->name, $accountName)) {
                throw new Exception(
                    "Your bank account name does not reasonably match your profile name."
                );
            }

            $user->bank_name               = $validated['bank_name'] ?? null;
            $user->bank_code               = $validated['bank_code'];
            $user->account_name            = $accountName;
            $user->account_number          = $result['raw']['accountNumber'] ?? $validated['account_number'];
            $user->bank_details_updated_at = now();
            unset($validated['bank_name'], $validated['account_name'], $validated['account_number'], $validated['bank_code']);
        }


        $user->fill($validated);
        $user->save();

        return ['user' => $user];
    }

    private static function namesLooselyMatch(string $profileName, string $bankName): bool
    {
        $normalize = function ($name) {
            $name = strtoupper($name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = preg_replace('/[^A-Z\s]/', '', $name);
            return trim($name);
        };

        $profile = $normalize($profileName);
        $bank    = $normalize($bankName);

        $profileParts = explode(' ', $profile);
        $bankParts    = explode(' ', $bank);

        // Require at least FIRST + LAST name overlap
        return count(array_intersect($profileParts, $bankParts)) >= 2;
    }
}
