<?php

namespace App\Services;

use App\Mail\WalletCreditedMail;
use Illuminate\Support\Facades\Mail;

class WalletNotificationService
{
    public function notifyCredit($user, $amount, $reference, $sender)
    {
        $senderName = $sender->name;

        try {
            if ($user->email) {
                Mail::to($user->email)->send(
                    new WalletCreditedMail(
                        $user,
                        $amount,
                        $reference,
                        $senderName
                    )
                );
            }

            if ($user->phone) {
                app(TermiiService::class)->sendSms(
                    $user->phone,
                    "Hello {$user->name}, your wallet has been credited with ₦" . number_format($amount, 2)
                );
            }

            if ($user->whatsapp_number) {
                app(TwilioService::class)->sendWhatsAppMessage(
                    $user->whatsapp_number,
                    "Hi {$user->name}, your wallet was credited with ₦" . number_format($amount, 2) . ". Ref: {$reference}"
                );
            }
        } catch (\Throwable $e) {
            logError("Wallet credit notifications failed", $e);
        }
    }
}
