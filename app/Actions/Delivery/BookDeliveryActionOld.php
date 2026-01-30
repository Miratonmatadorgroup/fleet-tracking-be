<?php
namespace App\Actions\Delivery;

use App\Models\User;
use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Support\Str;
use App\Enums\PackageTypeEnums;
use App\Services\PricingServiceOld;
use App\Enums\PaymentStatusEnums;
use App\Enums\DeliveryStatusEnums;
use App\DTOs\Delivery\BookDeliveryDTOOld;


class BookDeliveryActionOld
{
    public function __construct(protected PricingServiceOld $pricingService) {}

    public function execute(BookDeliveryDTOOld $dto, User $user): Delivery
    {
        $validated = $dto->validated;

        $restrictedModes = [
            PackageTypeEnums::DOCUMENTS->value => ['bike', 'van', 'air'],
            PackageTypeEnums::ELECTRONICS->value => ['van', 'truck', 'air'],
            PackageTypeEnums::CLOTHING->value => ['bike', 'van', 'truck', 'air', 'ship', 'bus', 'boat'],
            PackageTypeEnums::FOOD_ITEMS->value => ['bike', 'van'],
            PackageTypeEnums::FRAGILE_ITEMS->value => ['van', 'truck', 'air'],
            PackageTypeEnums::OTHERS->value => ['bike', 'van', 'truck', 'air', 'ship', 'bus', 'boat'],
        ];

        $packageType = $validated['package_type'];
        $transportMode = $validated['mode_of_transportation'];

        if (array_key_exists($packageType, $restrictedModes) &&
            !in_array($transportMode, $restrictedModes[$packageType])) {
            throw new \Exception("The selected mode of transportation ('$transportMode') is not allowed for package type '$packageType'.", 422);
        }

        $pricing = $this->pricingService->calculate(
            $transportMode,
            $validated['package_weight'],
            $validated['pickup_location'],
            $validated['dropoff_location'],
            $validated['delivery_type']
        );

        $finalSenderName = $validated['sender_name'] ?? $user->name;
        $finalSenderPhone = $validated['sender_phone'] ?? $user->phone ?? null;

        $delivery = Delivery::create([
            'customer_id' => $user->id,
            'pickup_location' => $validated['pickup_location'],
            'dropoff_location' => $validated['dropoff_location'],
            'mode_of_transportation' => $transportMode,
            'package_description' => $validated['package_description'],
            'package_weight' => $validated['package_weight'],
            'delivery_date' => $validated['delivery_date'],
            'delivery_time' => $validated['delivery_time'],
            'receiver_name' => $validated['receiver_name'],
            'receiver_phone' => $validated['receiver_phone'],
            'sender_name' => $finalSenderName,
            'sender_phone' => $finalSenderPhone,
            'package_type' => $packageType,
            'other_package_type' => $validated['other_package_type'] ?? null,
            'subtotal' => $pricing['subtotal'],
            'tax' => $pricing['tax'],
            'total_price' => $pricing['total'],
            'delivery_type' => $validated['delivery_type'],
            'estimated_days' => $pricing['estimated_days'],
            'status' => DeliveryStatusEnums::PENDING_PAYMENT->value,
        ]);

        Payment::create([
            'id' => Str::uuid(),
            'delivery_id' => $delivery->id,
            'user_id' => $user->id,
            'status' => PaymentStatusEnums::PENDING->value,
            'reference' => 'REF-' . strtoupper(Str::random(10)),
            'amount' => $delivery->total_price,
            'gateway' => config('payments.gateway_class'),
        ]);

        return $delivery->fresh();
    }
}
