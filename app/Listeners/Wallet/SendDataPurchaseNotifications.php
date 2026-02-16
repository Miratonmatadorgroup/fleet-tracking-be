<?php

namespace App\Listeners\Wallet;

use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Mail\DataPurchaseReceiptMail;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Events\Wallet\DataPurchaseCompleted;
use App\Events\Wallet\WalletPurchaseCompleted;
use App\Notifications\User\DataPurchaseNotification;

class SendDataPurchaseNotifications
{
    public function handle(DataPurchaseCompleted $event): void
    {
        $user = $event->user;
        $txn  = $event->transaction;
        $meta = $event->payload;

        if ($txn->method !== WalletTransactionMethodEnums::DATA) {
            return;
        }

        if ($txn->status !== WalletTransactionStatusEnums::SUCCESS) {
            return;
        }

        $phone    = $meta['phone'] ?? null;
        $provider = $meta['provider'] ?? null;
        $units    = $meta['units'] ?? 'N/A';

        // If payload is incomplete, stop silently
        if (!$phone || !$provider) {
            return;
        }

        $user->notify(new DataPurchaseNotification(
            amount: $txn->amount,
            phone: $phone,
            provider: $provider,
            reference: $txn->reference,
            units: $units
        ));

        if ($user->phone_verified_at && $user->phone) {
            $units = $meta['units'] ?? 'N/A';

            app(TermiiService::class)->sendSms(
                $user->phone,
                "Your data purchase was successful\n" .
                    "Data: {$units}\n" .
                    "Phone: {$meta['phone']}\n" .
                    "Amount: ₦" . number_format($txn->amount, 2) . "\n" .
                    "Ref: {$txn->reference}"
            );
        }

        if ($user->whatsapp_number_verified_at && $user->whatsapp_number) {
            $units = $meta['units'] ?? 'N/A';

            app(TwilioService::class)->sendWhatsAppMessage(
                $user->whatsapp_number,
                "Your data purchase was successful\n" .
                    "Data: {$units}\n" .
                    "Phone: {$meta['phone']}\n" .
                    "Amount: ₦" . number_format($txn->amount, 2) . "\n" .
                    "Ref: {$txn->reference}"
            );
        }


        if ($user->email_verified_at) {
            Mail::to($user->email)->send(
                new DataPurchaseReceiptMail(
                    $user,
                    $txn->amount,
                    $meta['phone'],
                    $meta['provider'],
                    $txn->reference,
                    units: $meta['units']
                )
            );
        }
    }
}
