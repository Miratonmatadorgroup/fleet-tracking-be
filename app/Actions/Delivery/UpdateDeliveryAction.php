<?php

namespace App\Actions\Delivery;

use App\Models\Payment;
use App\Models\Delivery;
use App\Models\TransportMode;
use App\Enums\PackageTypeEnums;
use App\Services\DriverService;
use App\Services\PricingService;
use App\Enums\TransportModeEnums;
use App\Services\DiscountService;
use App\Enums\DeliveryStatusEnums;
use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Log;
use App\Services\DeliveryTimeService;
use Illuminate\Support\Facades\Storage;
use App\DTOs\Delivery\UpdateDeliveryDTO;
use Illuminate\Validation\ValidationException;

class UpdateDeliveryAction
{
    public function __construct(
        protected PricingService $pricingService,
        protected DeliveryTimeService $deliveryTimeService,
        protected DriverService $driverService,
    ) {}

    public function execute(UpdateDeliveryDTO $dto, Delivery $delivery): array
    {
        $deliveryNotice = null;


        $existingPics = $delivery->delivery_pics ?? [];

        // Handle removed pictures
        $picsToRemove = request()->input('delivery_pics_remove', []);
        $updatedPics = array_values(array_diff($existingPics, $picsToRemove));

        // Delete removed images from storage
        foreach ($picsToRemove as $pic) {
            Storage::disk('public')->delete($pic);
        }

        // Handle newly uploaded pictures
        $newPics = [];
        if (request()->hasFile('delivery_pics')) {
            $files = request()->file('delivery_pics');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $image) {
                $path = $image->store('delivery_pics', 'public');
                $newPics[] = $path;
            }
        }

        // Combine remaining + new pics
        $finalPics = array_merge($updatedPics, $newPics);

        // Set into DTO
        $dto->data['delivery_pics'] = $finalPics;


        if ($delivery->status === DeliveryStatusEnums::BOOKED) {
            throw new \Exception('This delivery has already been booked and cannot be updated.', 403);
        }

        $shouldRecalculate = false;
        $pricingFields = [
            'pickup_location',
            'dropoff_location',
            'mode_of_transportation',
            'delivery_type',
            'package_weight',
        ];

        foreach ($pricingFields as $field) {
            if (array_key_exists($field, $dto->data) && $dto->data[$field] != $delivery->{$field}) {
                $shouldRecalculate = true;
                break;
            }
        }

        if (!$shouldRecalculate && $delivery->discount_amount) {
            $dto->data['discount_id'] = $delivery->discount_id;
            $dto->data['discount_amount'] = $delivery->discount_amount;
            $dto->data['total_price'] = $delivery->total_price;
        }

        if ($shouldRecalculate) {
            $pickupLocation   = $dto->data['pickup_location'] ?? $delivery->pickup_location;
            $dropoffLocation  = $dto->data['dropoff_location'] ?? $delivery->dropoff_location;
            $modeEnum         = TransportModeEnums::from($dto->data['mode_of_transportation'] ?? $delivery->mode_of_transportation);
            $deliveryType     = $dto->data['delivery_type'] ?? $delivery->delivery_type;
            $packageType      = $dto->data['package_type'] ?? $delivery->package_type;


            /**
             * Package type restrictions
             * These represent *disallowed* modes for each package type
             */
            $restrictedModes = [
                PackageTypeEnums::DOCUMENTS->value     => ['bike', 'van', 'air'],
                PackageTypeEnums::ELECTRONICS->value   => ['van', 'truck', 'air'],
                PackageTypeEnums::CLOTHING->value      => ['bike', 'van', 'truck', 'air', 'ship', 'bus', 'boat'],
                PackageTypeEnums::FOOD_ITEMS->value    => ['bike', 'van'],
                PackageTypeEnums::FRAGILE_ITEMS->value => ['van', 'truck', 'air'],
                PackageTypeEnums::OTHERS->value        => ['bike', 'van', 'truck', 'air', 'ship', 'bus', 'boat'],
            ];

            if (
                array_key_exists($packageType, $restrictedModes) &&
                !in_array($modeEnum->value, $restrictedModes[$packageType])
            ) {
                throw ValidationException::withMessages([
                    'mode_of_transportation' => "The selected mode of transportation ('{$modeEnum->value}') is not allowed for package type '{$packageType}'."
                ]);
            }

            //Coordinates + distance + duration from Google API
            $coordinates = app(GoogleMapsService::class)->getCoordinatesAndDistance(
                pickupAddress: $pickupLocation,
                dropoffAddress: $dropoffLocation,
                mode: $modeEnum
            );

            // Reverse geocode pickup + dropoff
            $pickupInfo = app(GoogleMapsService::class)->reverseGeocode(
                $coordinates['pickup_latitude'],
                $coordinates['pickup_longitude']
            );

            $dropoffInfo = app(GoogleMapsService::class)->reverseGeocode(
                $coordinates['dropoff_latitude'],
                $coordinates['dropoff_longitude']
            );

            // Block deliveries outside Lagos
            if ($pickupInfo && isset($pickupInfo['state'])) {
                if (strtolower($pickupInfo['state']) !== 'lagos') {
                    throw new \Exception("Delivery is not available for now outside Lagos.", 422);
                }
            }

            $modeOfTransport = $modeEnum->value; // default user choice

            if ($pickupInfo && $dropoffInfo) {
                // International delivery
                if (strcasecmp($pickupInfo['country'], $dropoffInfo['country']) !== 0) {
                    $modeOfTransport = TransportModeEnums::AIR->value;
                    if (($dto->data['package_weight'] ?? $delivery->package_weight) > 50) {
                        $modeOfTransport = TransportModeEnums::SHIP->value;
                    }

                    $deliveryNotice = "Delivery outside Nigeria will take longer for now";

                    Log::info('International delivery detected (UpdateDeliveryAction)', [
                        'pickup_country'  => $pickupInfo['country'],
                        'dropoff_country' => $dropoffInfo['country'],
                        'message'         => 'Delivery outside Nigeria will take longer for now',
                    ]);
                }
                // Domestic but inter-state
                elseif (strcasecmp($pickupInfo['state'], $dropoffInfo['state']) !== 0) {
                    $dynamicMode = $this->driverService->getBestAvailableModeForDelivery(
                        $coordinates['pickup_latitude'],
                        $coordinates['pickup_longitude'],
                        $coordinates['dropoff_latitude'],
                        $coordinates['dropoff_longitude']
                    );

                    $modeOfTransport = $dynamicMode ?? TransportModeEnums::VAN->value;
                }
            }

            // Update enum (in case mode was switched dynamically)
            $modeEnum = TransportModeEnums::from($modeOfTransport);
             $transportMode = TransportMode::with(['driver:id,id,is_flagged,flag_reason'])
            ->where('type', $modeEnum->value)
            ->firstOrFail();

        // Validate driver and transport mode availability
        $driver = $this->driverService->checkDriverAndModeStatus($transportMode);

        // Use driver & transportMode for dynamic logic if needed
        $modeOfTransport = $transportMode->type instanceof \BackedEnum
            ? $transportMode->type->value
            : (string) $transportMode->type;


            //Pricing (subtotal, tax, total, eta, duration)
            $pricing = $this->pricingService->calculatePriceAndETA(
                $pickupLocation,
                $dropoffLocation,
                $modeEnum,
                $deliveryType
            );

            //Duration → Days
            $estimatedDays = $this->deliveryTimeService->calculateEstimatedDays(
                $pricing['duration_minutes'] ?? 0
            );

            $discount = app(DiscountService::class)->getUserDiscount($delivery->customer);

            $discountAmount = 0;

            if ($discount) {
                $discountAmount = round(($discount->percentage / 100) * $pricing['total'], 2);
                $pricing['total'] = max($pricing['total'] - $discountAmount, 0);
            }

            // push discount back into DTO
            $dto->data['discount_id'] = $discount->id ?? null;
            $dto->data['discount_amount'] = $discountAmount;
            $dto->data['total_price'] = $pricing['total'];


            // Merge new values
            $dto->data = array_merge($dto->data, [
                'mode_of_transportation' => $modeEnum->value,
                'subtotal'          => $pricing['subtotal'],
                'tax'               => $pricing['tax'],
                'total_price'       => $pricing['total'],
                'estimated_days'    => $estimatedDays,
                'estimated_arrival' => $pricing['eta'],

                'pickup_latitude'   => $coordinates['pickup_latitude'],
                'pickup_longitude'  => $coordinates['pickup_longitude'],
                'dropoff_latitude'  => $coordinates['dropoff_latitude'],
                'dropoff_longitude' => $coordinates['dropoff_longitude'],
                'distance_km'       => $coordinates['distance_km'],
                'duration_minutes'  => $coordinates['duration_minutes'],
                'delivery_pics' => $finalPics,

            ]);

            //Sync payment
            $payment = Payment::where('delivery_id', $delivery->id)->first();
            if ($payment) {
                $payment->amount = $pricing['total'];
                $payment->save();
            }
        }

        // Update delivery
        $delivery->update($dto->data);

        //If client overrides amount, persist in Payment
        if ($dto->amount) {
            $payment = Payment::where('delivery_id', $delivery->id)->first();
            if ($payment) {
                $payment->amount = $dto->amount;
                $payment->save();
            }
        }

        return ['delivery' => $delivery->fresh(), 'message'   => $deliveryNotice ?? null,];
    }
}
