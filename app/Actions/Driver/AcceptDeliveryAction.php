<?php

namespace App\Actions\Driver;

use App\Models\Driver;
use App\Models\Delivery;
use App\Models\TransportMode;
use App\Enums\DriverStatusEnums;
use App\Enums\DeliveryStatusEnums;
use App\DTOs\Driver\AcceptDeliveryDTO;
use Illuminate\Validation\ValidationException;

class AcceptDeliveryAction
{
    public function execute(AcceptDeliveryDTO $dto): Delivery
    {
        $driver = Driver::where('user_id', $dto->user->id)->first();

        if (!$driver) {
            throw ValidationException::withMessages([
                'driver' => ['Driver profile not found for this user.']
            ]);
        }

        $delivery = Delivery::where('id', $dto->deliveryId)
            ->where('driver_id', $driver->id)
            ->whereIn('status', [
                DeliveryStatusEnums::BOOKED->value,
                DeliveryStatusEnums::QUEUED->value,
            ])
            ->first();

        if (!$delivery) {
            throw ValidationException::withMessages([
                'delivery' => ['Delivery not found or already accepted.']
            ]);
        }

        $transportMode = TransportMode::find($delivery->transport_mode_id);

        if ($transportMode && $transportMode->partner_id) {
            $delivery->partner_id = $transportMode->partner_id;
        }

        $delivery->status = DeliveryStatusEnums::IN_TRANSIT;
        $delivery->save();

        $driver->status = DriverStatusEnums::UNAVAILABLE;
        $driver->save();

        return $delivery->fresh();
    }
}
