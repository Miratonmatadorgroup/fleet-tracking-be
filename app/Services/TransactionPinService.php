<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TransactionPinService
{
    public function createPin(User $user, string $pin)
    {
        if ($user->transaction_pin) {
            throw new \Exception("Transaction PIN already exists.");
        }

        $user->transaction_pin = Hash::make($pin);
        $user->save();
    }

    public function changePin(User $user, string $oldPin, string $newPin)
    {
        if (! Hash::check($oldPin, $user->transaction_pin)) {
            throw new \Exception("Old PIN is incorrect.");
        }

        $user->transaction_pin = Hash::make($newPin);
        $user->save();
    }

    public function checkPin(User $user, string $pin)
    {
        if (! $user->transaction_pin) {
            throw new \Exception("You need to create a transaction PIN first.");
        }

        if (! Hash::check($pin, $user->transaction_pin)) {
            throw new \Exception("Invalid transaction PIN.");
        }

        return true;
    }

    public function generateResetPinOTP(User $user)
    {
        $otp = rand(100000, 999999);

        $user->pin_reset_otp = $otp;
        $user->pin_reset_otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        return $otp;
    }

    public function validateResetOTP(User $user, string $otp)
    {
        if ($user->pin_reset_otp !== $otp) {
            throw new \Exception("Invalid OTP.");
        }

        if (Carbon::now()->greaterThan($user->pin_reset_otp_expires_at)) {
            throw new \Exception("OTP expired.");
        }
    }

    public function resetPin(User $user, string $newPin)
    {
        $user->transaction_pin = Hash::make($newPin);
        $user->pin_reset_otp = null;
        $user->pin_reset_otp_expires_at = null;
        $user->save();
    }
}
