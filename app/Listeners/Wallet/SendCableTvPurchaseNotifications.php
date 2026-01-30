<?php

namespace App\Listeners\Wallet;

use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Mail\CableTvPurchaseReceiptMail;
use Illuminate\Queue\InteractsWithQueue;
use App\Enums\WalletTransactionStatusEnums;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\Wallet\WalletPurchaseCompleted;
use App\Events\Wallet\CableTvPurchaseCompleted;
use App\Notifications\User\AirtimePurchaseNotification;
use App\Notifications\User\CableTvPurchaseNotification;

class SendCableTvPurchaseNotifications
{
    public function handle(CableTvPurchaseCompleted $event): void
    {
        $user = $event->user;
        $txn  = $event->transaction;
        $meta = $event->payload;

        if (!in_array($txn->status, [
            WalletTransactionStatusEnums::SUCCESS,
            WalletTransactionStatusEnums::PENDING,
        ])) {
            return;
        }

        $user->notify(new CableTvPurchaseNotification(
            amount: $txn->amount,
            decoder: $meta['decoder_number'],
            provider: $meta['provider'],
            package: $meta['package'],
            reference: $txn->reference,
            status: $txn->status
        ));

        // SMS
        if ($user->phone_verified_at && $user->phone) {
            $msg = $txn->status === WalletTransactionStatusEnums::SUCCESS
                ? "Your {$meta['provider']} {$meta['package']} subscription for decoder {$meta['decoder_number']} was successful."
                : "Your {$meta['provider']} {$meta['package']} subscription is being processed.";

            app(TermiiService::class)->sendSms($user->phone, $msg);
        }


         if ($user->whatsapp_number_verified_at && $user->whatsapp_number) {
            $msg = $txn->status === WalletTransactionStatusEnums::SUCCESS
                ? "Your {$meta['provider']} {$meta['package']} subscription for decoder {$meta['decoder_number']} was successful."
                : "Your {$meta['provider']} {$meta['package']} subscription is being processed.";

            app(TwilioService::class)->sendWhatsAppMessage($user->whatsapp_number, $msg);
        }

        // Email only on SUCCESS
        if ($txn->status === WalletTransactionStatusEnums::SUCCESS && $user->email_verified_at) {
            Mail::to($user->email)->send(
                new CableTvPurchaseReceiptMail(
                    $user,
                    $txn->amount,
                    $meta['decoder_number'],
                    $meta['provider'],
                    $txn->reference,
                    $meta['package']
                )
            );
        }
    }
}
