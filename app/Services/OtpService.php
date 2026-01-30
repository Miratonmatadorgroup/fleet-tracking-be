<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OtpService
{
    public static function generateOtpForDriver(string $driverId): array
    {
        $otp = random_int(100000, 999999);
        $reference = (string) Str::uuid();

        $cacheKey = "otp_verification:{$reference}";

        // Store OTP + driverId in cac
        Cache::put($cacheKey, [
            'driver_id' => $driverId,
            'otp' => $otp,
        ], now()->addMinutes(5));

        return [
            'reference' => $reference,
            'otp' => $otp,
        ];
    }

    public static function verifyOtp(string $reference, string $otp): bool
    {
        $cacheKey = "otp_verification:{$reference}";

        $data = Cache::get($cacheKey);

        if (!$data || $data['otp'] !== $otp) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }
}
