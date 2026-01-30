<?php

namespace App\Listeners\Wallet;

use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Mail\AirtimePurchaseReceiptMail;
use Illuminate\Queue\InteractsWithQueue;
use App\Enums\WalletTransactionStatusEnums;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Mail\ElectricityPurchaseReceiptMail;
use App\Events\Wallet\WalletPurchaseCompleted;
use App\Events\Wallet\ElectricityPurchaseCompleted;
use App\Notifications\User\AirtimePurchaseNotification;
use App\Notifications\User\ElectricityPurchaseNotification;


class SendElectricityPurchaseNotifications
{
    public function handle(ElectricityPurchaseCompleted $event): void
    {
        $user = $event->user;
        $txn  = $event->transaction;
        $meta = $event->payload;

        if ($txn->status !== WalletTransactionStatusEnums::SUCCESS) {
            return;
        }

        // Ensure electricity payload
        if (!isset($meta['meter'], $meta['disco'], $meta['phone'], $meta['vendtype'])) {
            return;
        }

        $response = $meta['_provider_response'] ?? [];

        $token = $response['token'] ?? null;
        $units = $response['units'] ?? null;

        // App notification (MATCHES constructor)
        $user->notify(new ElectricityPurchaseNotification(
            amount: $txn->amount,
            phone: $meta['phone'],
            provider: $meta['disco'],
            meter: $meta['meter'],
            vendtype: $meta['vendtype']
        ));

        // SMS
        if ($user->phone_verified_at && $user->phone) {
            app(TermiiService::class)->sendSms(
                $user->phone,
                "Electricity purchase successful.
                Meter: {$meta['meter']}
                Token: {$token}
                Units: {$units}"
            );
        }

        // WhatsApp
        if ($user->whatsapp_number_verified_at && $user->whatsapp_number) {
            app(TwilioService::class)->sendWhatsAppMessage(
                $user->whatsapp_number,
                "Electricity purchase successful.
                Meter: {$meta['meter']}
                Token: {$token}
                Units: {$units}"
            );
        }

        // Email
        if ($user->email_verified_at) {
            Mail::to($user->email)->send(
                new ElectricityPurchaseReceiptMail(
                    $user,
                    $txn->amount,
                    $meta['phone'],
                    $meta['disco'],
                    $txn->reference,
                    $units,
                    $token
                )
            );
        }
    }
}
