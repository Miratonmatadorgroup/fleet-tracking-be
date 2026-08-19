<?php
namespace App\Actions\Authentication;


use App\DTOs\Authentication\DeleteAccountRequestDTO;
use App\Models\User;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\DeleteAccountOtpMail;

class SendDeleteAccountOtpAction
{
    public function execute(DeleteAccountRequestDTO $dto): void
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
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        $message = "Your Fleet Management account deletion OTP is: {$otp}";

        if ($user->email === $identifier) {

            Mail::to($user->email)
                ->send(new DeleteAccountOtpMail($otp, $user->name));

        } elseif ($user->phone === $identifier) {

            (new TermiiService)->sendSms($user->phone, $message);

        } elseif ($user->whatsapp_number === $identifier) {

            (new TwilioService)->sendWhatsAppMessage(
                $user->whatsapp_number,
                $message
            );

        } else {
            throw new \Exception("Unable to send OTP.", 422);
        }
    }
}

