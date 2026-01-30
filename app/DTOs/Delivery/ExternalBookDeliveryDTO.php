<?php

namespace App\DTOs\Delivery;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\PackageTypeEnums;

class ExternalBookDeliveryDTO
{
    public function __construct(
        public readonly string $pickupLocation,
        public readonly string $dropoffLocation,
        public readonly string $senderName,
        public readonly string $senderPhone,
        public readonly ?string $packageDescription,
        public readonly float $packageWeight,
        public readonly string $modeOfTransportation,
        public readonly string $deliveryType,
        public readonly string $deliveryDate,
        public readonly string $deliveryTime,
        public readonly string $receiverName,
        public readonly string $receiverPhone,
        public readonly PackageTypeEnums $packageType,
        public readonly ?string $otherPackageType,
        public readonly string $apiClientName,
        public readonly string $apiClientId,
        public readonly ?string $customerId,
        public readonly ?string $externalCustomerRef,
        public readonly ?float $amount,
        public readonly ?float $price_override,
        public readonly ?float $commission_override,
        public readonly ?float $original_price,
         public readonly ?string $pin,

        /** @var array<string> */
        public readonly array $deliveryPictures = [],
    ) {}

    public static function fromRequest(Request $request, ApiClient $apiClient, array $deliveryPictures = []): self
    {
        return new self(
            pickupLocation: $request->input('pickup_location', 'No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.'),
            dropoffLocation: $request->input('dropoff_location'),
            senderName: $request->input('sender_name'),
            senderPhone: $request->input('sender_phone'),
            packageDescription: $request->input('package_description'),
            packageWeight: (float) $request->input('package_weight', 1.5),
            modeOfTransportation: $request->input('mode_of_transportation', 'bike'),
            deliveryType: $request->input('delivery_type', 'standard'),
            deliveryDate: $request->input('delivery_date', now()->addDay()->toDateString()),
            deliveryTime: $request->input('delivery_time', '09:00:00'),
            receiverName: $request->input('receiver_name'),
            receiverPhone: $request->input('receiver_phone'),

            packageType: PackageTypeEnums::tryFrom(
                $request->input('package_type', PackageTypeEnums::DOCUMENTS->value)
            ) ?? PackageTypeEnums::OTHERS,

            otherPackageType: $request->input('other_package_type'),

            apiClientName: Str::limit(
                $request->input('api_client_name', $apiClient->name),
                100
            ),
            apiClientId: $apiClient->id,

            customerId: $request->user()->id
                ?? $request->header('X-External-User-Id')
                ?? config('services.logistics.customer_id'),

            externalCustomerRef: $request->header('X-External-User-Id'),

            amount: $request->input('amount'),
            price_override: $request->input('price_override'),
            commission_override: $request->input('commission_override'),
            original_price: $request->input('original_price'),

            pin: $request->input('pin'),

            deliveryPictures: $deliveryPictures,
        );
    }
}
