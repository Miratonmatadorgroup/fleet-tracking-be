<?php

namespace App\Actions\Delivery;

use App\Models\User;
use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Support\Str;
use App\Models\TransportMode;
use App\Enums\PackageTypeEnums;
use App\Services\DriverService;
use App\Services\PricingService;
use App\Enums\PaymentStatusEnums;
use App\Enums\TransportModeEnums;
use App\Services\DiscountService;
use App\Enums\DeliveryStatusEnums;
use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Log;
use App\Services\DeliveryTimeService;
use App\DTOs\Delivery\BookDeliveryDTO;

class BookDeliveryAction
{
    protected PricingService $pricingService;
    protected DeliveryTimeService $deliveryTimeService;

    public function __construct(
        PricingService $pricingService,
        DeliveryTimeService $deliveryTimeService,
        protected DriverService $driverService,
        protected DiscountService $discountService
    ) {
        $this->pricingService = $pricingService;
        $this->deliveryTimeService = $deliveryTimeService;
        $this->driverService = $driverService;
    }


    public function execute(BookDeliveryDTO $dto, User $user): array
    {
        $validated = $dto->validated;
        $deliveryNotice = null;

        $deliveryPicsPaths = [];
        if (request()->hasFile('delivery_pics')) {
            $files = request()->file('delivery_pics');
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $image) {
                $path = $image->store('delivery_pics', 'public');
                $deliveryPicsPaths[] = $path;
            }
        }

        // Restriction logic
        $restrictedModes = [
            PackageTypeEnums::DOCUMENTS->value   => ['bike', 'van', 'air'],
            PackageTypeEnums::ELECTRONICS->value => ['van', 'truck', 'air'],
            PackageTypeEnums::CLOTHING->value    => ['bike', 'van', 'truck', 'air', 'ship', 'bus', 'boat'],
            PackageTypeEnums::FOOD_ITEMS->value  => ['bike', 'van'],
            PackageTypeEnums::FRAGILE_ITEMS->value => ['van', 'truck', 'air'],
            PackageTypeEnums::OTHERS->value      => ['bike', 'van', 'truck', 'air', 'ship', 'bus', 'boat'],
        ];

        $packageType = $validated['package_type'];
        $transportModeEnum = TransportModeEnums::from($validated['mode_of_transportation']);
        $transportMode = TransportMode::with(['driver:id,id,is_flagged,flag_reason'])
            ->where('type', $transportModeEnum->value)
            ->firstOrFail();

        // Validate driver and transport mode availability
        $driver = $this->driverService->checkDriverAndModeStatus($transportMode);

        // Use driver & transportMode for dynamic logic if needed
        $modeOfTransport = $transportMode->type instanceof \BackedEnum
            ? $transportMode->type->value
            : (string) $transportMode->type;


        if (
            array_key_exists($packageType, $restrictedModes) &&
            !in_array($transportModeEnum->value, $restrictedModes[$packageType])
        ) {
            throw new \Exception(
                "The selected mode of transportation ('{$transportModeEnum->value}') is not allowed for package type '$packageType'.",
                422
            );
        }

        //Calculate price + ETA (new PricingService)
        $pricing = $this->pricingService->calculatePriceAndETA(
            pickup: $validated['pickup_location'],
            dropoff: $validated['dropoff_location'],
            mode: $transportModeEnum,
            deliveryType: $validated['delivery_type'] ?? null
        );

        // Convert duration_minutes → estimated_days using DeliveryTimeService
        $estimatedDays = $this->deliveryTimeService->calculateEstimatedDays(
            $pricing['duration_minutes'] ?? 0
        );

        $finalSenderName = $validated['sender_name'] ?? $user->name;
        $finalSenderPhone = $validated['sender_phone'] ?? $user->phone ?? null;

        $coordinates = app(GoogleMapsService::class)->getCoordinatesAndDistance(
            pickupAddress: $validated['pickup_location'],
            dropoffAddress: $validated['dropoff_location'],
            mode: $transportModeEnum
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


        $modeOfTransport = $transportModeEnum->value; // default user choice

        // If pickup & dropoff exist, check state/country differences
        if ($pickupInfo && $dropoffInfo) {
            // First check if delivery is outside Nigeria
            if (strcasecmp($pickupInfo['country'], $dropoffInfo['country']) !== 0) {
                // International delivery → force Air or Ship
                $modeOfTransport = TransportModeEnums::AIR->value; // default
                if ($validated['package_weight'] > 50) {
                    $modeOfTransport = TransportModeEnums::SHIP->value;
                }

                $deliveryNotice = "Delivery outside Nigeria will take longer for now";

                // Log a friendly notice (not error)
                Log::info('International delivery detected', [
                    'pickup_country'  => $pickupInfo['country'],
                    'dropoff_country' => $dropoffInfo['country'],
                    'message'         => 'Delivery outside Nigeria will take longer for now',
                ]);
            }
            // Otherwise check if different states within the same country
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


        // Update enum
        $transportModeEnum = TransportModeEnums::from($modeOfTransport);

        //  Calculate base price before discount
        $baseTotal = $pricing['total'];

        $discount = $this->discountService->getUserDiscount($user);

        $discountAmount = 0;

        if ($discount) {
            $discountAmount = round(($discount->percentage / 100) * $baseTotal, 2);
            $pricing['total'] = max($baseTotal - $discountAmount, 0);
        }

        $delivery = Delivery::create([
            'customer_id'        => $user->id,
            'pickup_location'    => $validated['pickup_location'],
            'dropoff_location'   => $validated['dropoff_location'],
            'package_description' => $validated['package_description'],
            'mode_of_transportation' => $transportModeEnum->value,
            'package_weight'     => $validated['package_weight'],
            'delivery_date'      => $validated['delivery_date'],
            'delivery_time'      => $validated['delivery_time'],
            'receiver_name'      => $validated['receiver_name'],
            'receiver_phone'     => $validated['receiver_phone'],
            'sender_name'        => $finalSenderName,
            'sender_phone'       => $finalSenderPhone,
            'package_type'       => $packageType,
            'other_package_type' => $validated['other_package_type'] ?? null,
            'subtotal'           => $pricing['subtotal'],
            'tax'                => $pricing['tax'],
            'total_price'        => $pricing['total'],
            'discount_id'        => $discount->id ?? null,
            'discount_amount'    => $discountAmount ?? 0,
            'delivery_type'      => $validated['delivery_type'],
            'estimated_days'     => $estimatedDays,
            'estimated_arrival'  => $pricing['eta'],
            'status'             => DeliveryStatusEnums::PENDING_PAYMENT->value,
            'pickup_latitude'   => $coordinates['pickup_latitude'],
            'pickup_longitude'  => $coordinates['pickup_longitude'],
            'dropoff_latitude'  => $coordinates['dropoff_latitude'],
            'dropoff_longitude' => $coordinates['dropoff_longitude'],
            'distance_km'       => $coordinates['distance_km'],
            'duration_minutes'  => $coordinates['duration_minutes'],
            'delivery_pics'      => $deliveryPicsPaths,

        ]);

        Payment::create([
            'id'        => Str::uuid(),
            'delivery_id' => $delivery->id,
            'user_id'   => $user->id,
            'status'    => PaymentStatusEnums::PENDING->value,
            'reference' => 'REF-' . strtoupper(Str::random(10)),
            'amount'    => $delivery->total_price,
            'gateway'   => config('payments.gateway_class'),
        ]);

        return ['delivery' => $delivery->fresh(), 'message'   => $deliveryNotice ?? null,];
    }
}
