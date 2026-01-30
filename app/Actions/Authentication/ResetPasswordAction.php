<?php
namespace App\Actions\Authentication;


use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\DTOs\Authentication\ResetPasswordDTO;

class ResetPasswordAction
{
    public static function execute(ResetPasswordDTO $dto): void
    {
        if (filter_var($dto->identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $dto->identifier)->first();
        } elseif (preg_match('/^\+?[0-9]{10,15}$/', $dto->identifier)) {
            $user = User::where('phone', $dto->identifier)
                        ->orWhere('whatsapp_number', $dto->identifier)
                        ->first();
        } else {
            throw new \Exception("Invalid identifier format", 422);
        }

        if (
            !$user ||
            $user->otp_code != $dto->otp ||
            now()->gt($user->otp_expires_at)
        ) {
            throw new \Exception("Invalid or expired OTP", 422);
        }

        $user->password = Hash::make($dto->password);
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();
    }
}
