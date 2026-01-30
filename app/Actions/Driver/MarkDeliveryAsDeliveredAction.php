<?php
namespace App\Actions\Driver;


use App\Models\Delivery;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\DTOs\Driver\MarkAsDeliveredDTO;
use App\Mail\DeliveryMarkedAsDelivered;
use App\Notifications\User\DeliveryMarkedAsDeliveredNotification;

class MarkDeliveryAsDeliveredAction
{
    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {}

    public function execute(MarkAsDeliveredDTO $dto): Delivery
    {
        $delivery = Delivery::with('customer')
            ->where('id', $dto->deliveryId)
            ->where('driver_id', $dto->driverId)
            ->where('status', DeliveryStatusEnums::IN_TRANSIT)
            ->first();

        if (!$delivery) {
            throw new \Exception("Delivery not found or not in transit.", 404);
        }

        $delivery->update([
            'status' => DeliveryStatusEnums::DELIVERED,
        ]);

        $delivery->driver->update([
            'status' => DriverStatusEnums::AVAILABLE,
        ]);

        $this->notifySender($delivery);

        return $delivery->fresh();
    }

    protected function notifySender(Delivery $delivery): void
    {
        $customer = $delivery->customer;
        $driverName = $delivery->driver->name;
        $tracking = $delivery->tracking_number;

        $message = "Hi {$customer->name}, your LoopFreight delivery (Tracking No: {$tracking}) has been delivered by {$driverName}. Please contact the receiver and Complete Delivery from your dashboard.";

        try {
            if ($customer->email) {
                Mail::to($customer->email)->send(new DeliveryMarkedAsDelivered($delivery, $delivery->driver));
            }

            if ($customer->phone) {
                $this->termii->sendSms($customer->phone, $message);
            }

            if ($customer->whatsapp_number) {
                $this->twilio->sendWhatsAppMessage($customer->whatsapp_number, $message);
            }

             $customer->notify(new DeliveryMarkedAsDeliveredNotification($delivery));

        } catch (\Throwable $e) {
            Log::error('Notification to sender failed after delivery marked as delivered.', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
