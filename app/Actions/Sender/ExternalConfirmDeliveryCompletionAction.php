<?php

namespace App\Actions\Sender;

use App\Models\Delivery;
use App\Models\ApiClient;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Services\WalletService;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DeliveryCompletedNotification;
use App\Mail\ExternalDeliveryCompletedNotification;
use App\Events\Delivery\DeliveryCompletedConfirmedEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\Sender\ExternalConfirmDeliveryCompletionDTO;

class ExternalConfirmDeliveryCompletionAction
{
    public function execute(
        ExternalConfirmDeliveryCompletionDTO $dto,
        ApiClient $apiClient,
        TwilioService $twilio,
        TermiiService $termii
    ): array {
        $delivery = Delivery::with(['driver.user', 'customer'])
            ->where('tracking_number', $dto->trackingNumber)
            ->where('api_client_id', $apiClient->id)
            ->where('status', DeliveryStatusEnums::DELIVERED)
            ->first();

        if (!$delivery) {
            throw new ModelNotFoundException("Delivery not found, not delivered, or does not belong to this partner.");
        }

        // Mark as completed
        $delivery->status = DeliveryStatusEnums::COMPLETED;
        $delivery->save();

        // Release driver's commission
        if ($delivery->driver?->user) {
            WalletService::creditCommissions($delivery->driver->user, $delivery);
        }

        $driver = $delivery->driver;
        $tracking = $delivery->tracking_number;

        $message = "Delivery (Tracking No: {$tracking}) has been confirmed completed.";

        // Fire event for external partners
        event(new DeliveryCompletedConfirmedEvent($tracking));

        try {
            // Email to external customer
            if ($delivery->customer_email) {
                Mail::to($delivery->customer_email)
                    ->send(new ExternalDeliveryCompletedNotification($delivery, 'customer'));
            }

            // Email to internal driver
            if ($driver?->email) {
                Mail::to($driver->email)
                    ->send(new DeliveryCompletedNotification($delivery, 'driver'));
            }

            // SMS
            if ($driver?->phone) {
                $termii->sendSms($driver->phone, $message);
            }
            if ($delivery->customer_phone) {
                $termii->sendSms($delivery->customer_phone, $message);
            }

            // WhatsApp
            if ($driver?->whatsapp_number) {
                $twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
            }
            if ($delivery->customer_whatsapp_number) {
                $twilio->sendWhatsAppMessage($delivery->customer_whatsapp_number, $message);
            }
        } catch (\Throwable $e) {
            Log::error('External completion notifications failed', ['error' => $e->getMessage()]);
        }

        return ['message' => 'Delivery confirmed completed for external partner.'];
    }
}
