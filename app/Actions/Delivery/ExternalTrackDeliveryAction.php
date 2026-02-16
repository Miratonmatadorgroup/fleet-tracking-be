<?php

namespace App\Actions\Delivery;

use App\Models\Driver;
use App\Models\Payment;
use App\Models\Delivery;
use App\Models\DriverLocation;
use App\DTOs\Delivery\ExternalTrackDeliveryDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ExternalTrackDeliveryAction
{
    public function execute(ExternalTrackDeliveryDTO $dto): array
    {
        $query = Delivery::query()->where('api_client_id', $dto->apiClientId);

        if ($dto->trackingNumber) {
            $query->where('tracking_number', $dto->trackingNumber);
        } elseif ($dto->deliveryId) {
            $query->where('id', $dto->deliveryId);
        } elseif ($dto->externalCustomerRef) {
            $query->where('external_customer_ref', $dto->externalCustomerRef)
                ->latest('id');
        }

        /** @var Delivery $delivery */
        $delivery = $query->first();

        if (! $delivery) {
            throw new ModelNotFoundException(
                "Delivery not found for api_client_id={$dto->apiClientId}, " .
                    "tracking_number={$dto->trackingNumber}, " .
                    "delivery_id={$dto->deliveryId}, " .
                    "external_customer_ref={$dto->externalCustomerRef}"
            );
        }
        //payment (if any)
        $payment = Payment::where('delivery_id', $delivery->id)
            ->latest('id')
            ->first();

        //find driver
        $driver = null;
        if (isset($delivery->driver_id) && $delivery->driver_id) {
            $driver = Driver::find($delivery->driver_id);
        } elseif (method_exists($delivery, 'driver')) {
            $driver = $delivery->driver;
        }

        // driver live location
        $latestLocation = null;
        $locationHistory = collect();
        if ($delivery->driver_id) {
            $latestLocation = DriverLocation::where('driver_id', $delivery->driver_id)
                ->latest()
                ->first();

            $locationHistory = DriverLocation::where('driver_id', $delivery->driver_id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->reverse()
                ->values();
        }

        return [
            'delivery' => [
                'id'                 => $delivery->id,
                'tracking_number'    => $delivery->tracking_number,
                'waybill_number'    => $delivery->waybill_number,
                'status'             => $delivery->status,
                'pickup_location'    => $delivery->pickup_location,
                'dropoff_location'   => $delivery->dropoff_location,
                'distance_km'        => $delivery->distance_km,
                'duration_minutes'   => $delivery->duration_minutes,
                'estimated_arrival'  => $delivery->estimated_arrival,
                'delivery_date'      => $delivery->delivery_date,
                'delivery_time'      => $delivery->delivery_time,
                'receiver_name'      => $delivery->receiver_name,
                'receiver_phone'     => $delivery->receiver_phone,
                'package_description' => $delivery->package_description,
                'package_type'       => $delivery->package_type,
                'subtotal'           => $delivery->subtotal,
                'total_price'        => $delivery->total_price,
                'api_client_name'    => $delivery->api_client_name,
                'driver_location'      => $latestLocation,
                'driver_location_history' => $locationHistory,
            ],
            'driver' => $driver ? [
                'id'       => $driver->id,
                'name'     => $driver->name ?? ($delivery->driver_name ?? null),
                'phone'    => $driver->phone ?? null,
                'email'    => $driver->email ?? null,
                'whatsapp_number'    => $driver->whatsapp_number ?? null,

            ] : null,
            'payment' => $payment ? [
                'id'        => $payment->id,
                'status'    => $payment->status,
                'amount'    => $payment->amount,
                'currency'  => $payment->currency,
                'reference' => $payment->reference,
                'meta'      => $payment->meta,
            ] : null,
        ];
    }
}
