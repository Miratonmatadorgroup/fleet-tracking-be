<?php
namespace App\DTOs\Delivery;

class ConfirmInAppDeliveryCompletionAsAdminDTO
{
    public function __construct(
        public readonly string $deliveryId
    ) {}
}
