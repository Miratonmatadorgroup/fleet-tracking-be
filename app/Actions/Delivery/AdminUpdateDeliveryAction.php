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
use App\DTOs\Delivery\AdminUpdateDeliveryDTO;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;


class AdminUpdateDeliveryAction
{
    public function __construct(
        protected PricingService $pricingService,
        protected DeliveryTimeService $deliveryTimeService,
        protected DriverService $driverService,
        protected DiscountService $discountService
    ) {}

    public function execute(AdminUpdateDeliveryDTO $dto, Delivery $delivery): array
    {
        $deliveryNotice = null;
        $pricing = null;

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
            throw new HttpException(403, 'This delivery has already been booked and cannot be updated.');
        }

        // $modeEnum = TransportModeEnums::from($delivery->mode_of_transportation);
        $modeEnum = $delivery->mode_of_transportation instanceof TransportModeEnums
            ? $delivery->mode_of_transportation
            : TransportModeEnums::from($delivery->mode_of_transportation);

        $pricingFields = [
            'pickup_location',
            'dropoff_location',
            'mode_of_transportation',
            'delivery_type',
            'package_weight',
            'package_type',
        ];

        $shouldRecalculate = collect($pricingFields)->contains(function ($field) use ($dto, $delivery) {
            return array_key_exists($field, $dto->data) && $dto->data[$field] != $delivery->{$field};
        });

        if ($shouldRecalculate) {
            // Latest values
            $pickupLocation  = $dto->data['pickup_location'] ?? $delivery->pickup_location;
            $dropoffLocation = $dto->data['dropoff_location'] ?? $delivery->dropoff_location;
            $rawMode = $dto->data['mode_of_transportation']
                ?? $delivery->mode_of_transportation;

            $modeEnum = $rawMode instanceof TransportModeEnums
                ? $rawMode
                : TransportModeEnums::from($rawMode);

            $deliveryType    = $dto->data['delivery_type'] ?? $delivery->delivery_type;
            $packageType     = $dto->data['package_type'] ?? $delivery->package_type;


            /**
             * Package type restrictions (disallowed modes)
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
                    'mode_of_transportation' =>
                    "The selected mode of transportation ('{$modeEnum->value}') is not allowed for package type '{$packageType}'."
                ]);
            }

            $pricing = $this->pricingService->calculatePriceAndETA(
                $pickupLocation,
                $dropoffLocation,
                $modeEnum,
                $deliveryType
            );

            //ETA → days
            $estimatedDays = $this->deliveryTimeService->calculateEstimatedDays(
                $pricing['duration_minutes'] ?? 0
            );

            $baseTotal = $pricing['total'];

            $discount = $this->discountService->getUserDiscount($delivery->customer);

            $discountAmount = 0;

            if ($discount) {
                Log::info("Discount applied on update", [
                    'delivery_id' => $delivery->id,
                    'user_id' => $delivery->customer_id,
                    'discount_percentage' => $discount->percentage,
                    'discount_amount' => $discountAmount
                ]);
                $discountAmount = round(($discount->percentage / 100) * $baseTotal, 2);

                // update final total
                $pricing['total'] = max($baseTotal - $discountAmount, 0);
            }

            //Coordinates, distance & duration
            $coordinates = app(GoogleMapsService::class)->getCoordinatesAndDistance(
                pickupAddress: $pickupLocation,
                dropoffAddress: $dropoffLocation,
                mode: $modeEnum
            );


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

            $modeOfTransport = $modeEnum->value;

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
                        'message'         => $deliveryNotice,
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

            $dto->data = array_merge($dto->data, [
                'mode_of_transportation' => $modeEnum->value,
                'subtotal'          => $pricing['subtotal'],
                'tax'               => $pricing['tax'],
                'total_price'       => $pricing['total'],
                'estimated_days'    => $estimatedDays,
                'estimated_arrival' => $pricing['eta'],
                'discount_id'       => $discount->id ?? null,
                'discount_amount'   => $discountAmount ?? 0,

                'pickup_latitude'   => $coordinates['pickup_latitude'],
                'pickup_longitude'  => $coordinates['pickup_longitude'],
                'dropoff_latitude'  => $coordinates['dropoff_latitude'],
                'dropoff_longitude' => $coordinates['dropoff_longitude'],
                'distance_km'       => $coordinates['distance_km'],
                'duration_minutes'  => $coordinates['duration_minutes'],
                'delivery_pics' => $finalPics,

            ]);
        }

        $modeEnum = $modeEnum instanceof TransportModeEnums
            ? $modeEnum
            : TransportModeEnums::from($modeEnum);


        $dto->data['mode_of_transportation'] = $modeEnum->value;

        $payment = Payment::where('delivery_id', $delivery->id)->first();
        if ($payment && $pricing) {
            $payment->update([
                'amount' => $pricing['total'],
            ]);
        }

        // if ($payment) {

        //     $payment->update([
        //         'amount' => $dto->data['amount'] ?? ($pricing['total'] ?? $payment->amount),
        //     ]);
        // }

        $delivery->update($dto->data);

        return ['delivery' => $delivery->fresh(), 'message'   => $deliveryNotice ?? null,];
    }
}
