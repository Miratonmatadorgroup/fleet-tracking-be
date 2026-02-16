<?php

namespace App\DTOs\Delivery;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use App\Enums\PackageTypeEnums;

class ShanonoBookDeliveryDTO
{
    public function __construct(
        public readonly string $pickupLocation,
        public readonly string $dropoffLocation,
        public readonly string $customerName,
        public readonly string $customerPhone,
        public readonly string $customerWhatsappNumber,
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
    ) {}

    public static function fromRequest(Request $request, ApiClient $apiClient): self
    {
        $isBankingFlow = stripos($apiClient->name, 'shanono') !== false;

        return new self(
            pickupLocation: $request->input('pickup_location', 'No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.'),

            dropoffLocation: $request->input('dropoff_location'),

            customerName: $request->input('customer_name', 'Shanono Bank'),

            customerPhone: $request->input('customer_phone', '+2348000322367'),

            customerWhatsappNumber: $request->input('customer_whatsapp_number', '+2348000322367'),

            senderName: $request->input('sender_name', 'Shanono Bank'),

            senderPhone: $request->input('sender_phone', '+2348000322367'),

            packageDescription: $request->input('package_description', 'ATM CARD'),

            packageWeight: (float) $request->input('package_weight', 1.5),

            modeOfTransportation: $request->input('mode_of_transportation', 'bike'),

            deliveryType: $request->input('delivery_type', 'standard'),

            deliveryDate: $request->input('delivery_date',  now()->addDay()->toDateString()),

            deliveryTime: $request->input('delivery_time', '09:00:00'),

            receiverName: $request->input('receiver_name'),
            receiverPhone: $request->input('receiver_phone'),

             packageType: PackageTypeEnums::tryFrom(
                $request->input('package_type', PackageTypeEnums::DOCUMENTS->value)
            ) ?? PackageTypeEnums::OTHERS,

            otherPackageType: $request->input('other_package_type'),

            apiClientName: $request->input('api_client_name', $apiClient->name),
            apiClientId: $apiClient->id,

            customerId: $request->user()->id
                ?? $request->header('X-External-User-Id')
                ?? config('services.logistics.bank_customer_id'),

            externalCustomerRef: $request->header('X-External-User-Id'),

            amount: $request->input('amount'),
        );
    }
}
