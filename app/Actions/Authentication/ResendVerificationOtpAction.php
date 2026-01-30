<?php

namespace App\Actions\Authentication;

use App\Mail\SendOtpMail;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\DTOs\Authentication\ResendOtpDTO;

class ResendVerificationOtpAction
{
    public static function execute(ResendOtpDTO $dto): array
    {
        $pending = Cache::get($dto->reference);

        if (!$pending) {
            throw new \Exception("No pending request found.", 404);
        }

        $otp = (string) rand(100000, 999999);
        $pending['otp_code'] = $otp;
        $pending['otp_expires_at'] = now()->addMinutes(10);

        Cache::put($dto->reference, $pending, now()->addMinutes(10));

        $channel    = $pending['channel'] ?? null;
        $identifier = $pending['identifier'] ?? null;
        $name       = $pending['name'] ?? null;

        if ($channel === 'email') {
            Mail::to($identifier)->send(new SendOtpMail($otp, $name));
        } elseif ($channel === 'phone') {
            (new TermiiService())->sendSms($identifier, "Your LoopFreight verification code is: {$otp}");
        } elseif ($channel === 'whatsapp') {
            (new TwilioService())->sendWhatsAppMessage($identifier, "Your LoopFreight verification code is: {$otp}");
        } else {
            throw new \Exception("No valid contact method available.", 400);
        }

        return [
            'message'   => "Verification code resent via {$channel}.",
            'channel'   => $channel,
            'reference' => $dto->reference,
        ];
    }
}
