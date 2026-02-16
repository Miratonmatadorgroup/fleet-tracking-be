<?php

namespace App\Actions\Driver;

use App\Models\Delivery;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Services\WalletService;
use App\Enums\DriverStatusEnums;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Events\Delivery\DeliveryCompleted;
use App\Mail\DeliveryCompletedNotification;
use App\DTOs\Driver\ConfirmDeliveryByWaybillDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Notifications\User\DeliveryCompletedInAppNotification;

class ConfirmDeliveryByWaybillAction
{
    public function execute(ConfirmDeliveryByWaybillDTO $dto, TwilioService $twilio, TermiiService $termii): array
    {
        $delivery = Delivery::with(['driver.user', 'customer', 'transportMode.partner.user'])
            ->where('id', $dto->deliveryId)
            ->where('driver_id', $dto->driverId)
            ->where('status', DeliveryStatusEnums::IN_TRANSIT)
            ->first();

        if (!$delivery) {
            throw new ModelNotFoundException('Delivery not found or not in delivered state.');
        }

        Log::info('Partner check during delivery completion', [
            'delivery_id' => $delivery->id,
            'transport_mode_id' => $delivery->transport_mode_id,
            'transport_mode_partner_user' => $delivery->transportMode?->partner?->user?->id,
            'driver_partner_user' => $delivery->driver?->partner?->user?->id,
        ]);

        if ($delivery->waybill_number !== strtoupper(trim($dto->waybillNumber))) {
            throw new \Exception('Invalid waybill number provided.');
        }

        // Update status
        $delivery->status = DeliveryStatusEnums::COMPLETED;
        $delivery->save();

        if ($delivery->driver) {
            $delivery->driver->status = DriverStatusEnums::AVAILABLE;
            $delivery->driver->save();
        }

        // Trigger delivery completed event after response
        dispatch(function () use ($delivery) {
            event(new DeliveryCompleted($delivery));
        })->afterResponse();

        $driverUser = $delivery->driver?->user;
        $customer = $delivery->customer;
        $tracking = $delivery->tracking_number;

        // Pay driver commission
        if ($driverUser) {
            WalletService::creditCommissions($driverUser, $delivery);
            $driverUser->notify(new DeliveryCompletedInAppNotification($delivery, 'driver'));
        }

        if ($customer) {
            $customer->notify(new DeliveryCompletedInAppNotification($delivery, 'customer'));
        }

        $message = "LoopFreight Delivery (Tracking No: {$tracking}) has been confirmed completed.";

        try {
            // Email notifications
            if ($customer?->email) {
                Mail::to($customer->email)->send(new DeliveryCompletedNotification($delivery, 'customer'));
            }
            if ($driverUser?->email) {
                Mail::to($driverUser->email)->send(new DeliveryCompletedNotification($delivery, 'driver'));
            }

            // SMS
            if ($driverUser?->phone) {
                $termii->sendSms($driverUser->phone, $message);
            }
            if ($customer?->phone) {
                $termii->sendSms($customer->phone, $message);
            }

            // WhatsApp
            if ($driverUser?->whatsapp_number) {
                $twilio->sendWhatsAppMessage($driverUser->whatsapp_number, $message);
            }
            if ($customer?->whatsapp_number) {
                $twilio->sendWhatsAppMessage($customer->whatsapp_number, $message);
            }
        } catch (\Throwable $e) {
            Log::error('Completion notifications failed', ['error' => $e->getMessage()]);
        }

        return ['message' => 'Delivery confirmed successfully by driver.'];
    }
}
