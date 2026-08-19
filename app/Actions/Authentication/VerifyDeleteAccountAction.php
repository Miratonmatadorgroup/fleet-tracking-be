<?php

namespace App\Actions\Authentication;

use App\DTOs\Authentication\VerifyDeleteAccountDTO;
use App\Models\User;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyDeleteAccountAction
{
    public function execute(VerifyDeleteAccountDTO $dto, $request): void
    {
        $identifier = $dto->identifier;

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('whatsapp_number', $identifier)
            ->first();

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        if ($user->otp_code !== $dto->otp) {
            throw new HttpException(422, 'Invalid OTP.');
        }

        if (
            !$user->otp_expires_at ||
            now()->greaterThan($user->otp_expires_at)
        ) {
            throw new HttpException(422, 'OTP expired.');
        }

        if ($user->wallet && $user->wallet->balance > 0) {
            throw new HttpException(
                422,
                'You cannot delete your account with remaining wallet balance.'
            );
        }

        // Clear OTP
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        //Revoke Passport token (if authenticated)
        if ($request->user() && $request->user()->token()) {
            $request->user()->token()->revoke();
        }

        // Delete user
        $user->delete();
    }
}
