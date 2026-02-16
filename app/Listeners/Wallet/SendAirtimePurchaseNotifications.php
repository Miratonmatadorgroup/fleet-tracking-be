<?php

namespace App\Listeners\Wallet;

use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Mail\AirtimePurchaseReceiptMail;
use Illuminate\Queue\InteractsWithQueue;
use App\Enums\WalletTransactionStatusEnums;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\Wallet\WalletPurchaseCompleted;
use App\Events\Wallet\AirtimePurchaseCompleted;
use App\Notifications\User\AirtimePurchaseNotification;

class SendAirtimePurchaseNotifications
{
    public function handle(AirtimePurchaseCompleted $event): void
    {
        $user = $event->user;
        $txn  = $event->transaction;
        $meta = $event->payload;

        if ($txn->status !== WalletTransactionStatusEnums::SUCCESS) {
            return;
        }

        // App notification
        $phone    = $meta['phone'] ?? null;
        $provider = $meta['provider'] ?? null;

        // If this event is not airtime-related, bail out
        if (!$phone || !$provider) {
            return;
        }

        // App notification
        $user->notify(new AirtimePurchaseNotification(
            $txn->amount,
            $phone,
            $provider,
            $txn->reference
        ));

        //SMS
        if ($user->phone_verified_at && $user->phone) {
            app(TermiiService::class)->sendSms(
                $user->phone,
                "Your LoopFreight airtime purchase of ₦" .
                    number_format($txn->amount, 2) .
                    " to {$meta['phone']} was successful."
            );
        }

        // WhatsApp
        if ($user->whatsapp_number_verified_at && $user->whatsapp_number) {
            app(TwilioService::class)->sendWhatsAppMessage(
                $user->whatsapp_number,
                "Your LoopFreight airtime purchase of ₦" .
                    number_format($txn->amount, 2) .
                    " to {$meta['phone']} was successful."
            );
        }

        //Email
        if ($user->email_verified_at) {
            Mail::to($user->email)->send(
                new AirtimePurchaseReceiptMail(
                    $user,
                    $txn->amount,
                    $meta['phone'],
                    $meta['provider'],
                    $txn->reference
                )
            );
        }
    }
}
