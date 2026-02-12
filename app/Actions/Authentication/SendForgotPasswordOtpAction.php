<?php
namespace App\Actions\Authentication;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\DTOs\Authentication\ForgotPasswordDTO;

class SendForgotPasswordOtpAction
{
    public static function execute(ForgotPasswordDTO $dto): array
    {
        $identifier = $dto->identifier;

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('whatsapp_number', $identifier)
            ->first();

        if (!$user) {
            throw new \Exception("User not found", 404);
        }

        $otp = rand(100000, 999999);
        $user->otp_code = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        $message = "Your Fleet Management verification Otp is:{$otp}";

        if ($user->email === $identifier) {
            Mail::send('emails.forgot-password-otp', ['user' => $user, 'otp' => $otp], function ($mail) use ($user) {
                $mail->to($user->email)->subject('Reset Password Code');
            });
            $channel = 'email';
        } elseif ($user->phone === $identifier) {
            (new TermiiService)->sendSms($user->phone, $message);
            $channel = 'phone';
        } elseif ($user->whatsapp_number === $identifier) {
            (new TwilioService)->sendWhatsAppMessage($user->whatsapp_number, $message);
            $channel = 'whatsapp';
        } else {
            throw new \Exception("Unable to send OTP. Invalid identifier match.", 422);
        }

        return [
            'otp'     => $otp,
            'channel' => $channel,
        ];
    }
}
