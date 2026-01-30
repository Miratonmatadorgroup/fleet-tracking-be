<?php
namespace App\DTOs\Delivery;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExternalTrackDeliveryDTO
{
    public function __construct(
        public readonly ?string $trackingNumber,
        public readonly ?string $externalCustomerRef,
        public readonly ?int $deliveryId,
        public readonly string $apiClientName,
        public readonly string|int $apiClientId,
    ) {}

    public static function fromRequest(Request $request, ApiClient $apiClient): self
    {
        $data = $request->only(['tracking_number', 'external_customer_ref', 'delivery_id']);

        $validator = Validator::make($data, [
            'tracking_number'      => 'nullable|string',
            'external_customer_ref'=> 'nullable|string',
            'delivery_id'          => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        //require at least one identifier
        if (empty($data['tracking_number']) && empty($data['delivery_id']) && empty($data['external_customer_ref'])) {
            throw ValidationException::withMessages([
                'identifier' => 'Provide one of: tracking_number, delivery_id, or external_customer_ref.'
            ]);
        }

        return new self(
            trackingNumber: $data['tracking_number'] ?? null,
            externalCustomerRef: $data['external_customer_ref'] ?? null,
            deliveryId: isset($data['delivery_id']) ? (int) $data['delivery_id'] : null,
            apiClientName: $request->input('api_client_name', $apiClient->name),
            apiClientId: $apiClient->id,
        );
    }
}
