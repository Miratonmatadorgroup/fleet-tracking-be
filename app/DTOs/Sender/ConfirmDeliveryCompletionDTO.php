<?php
namespace App\DTOs\Sender;

class ConfirmDeliveryCompletionDTO
{
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $customerId,
    ) {}
}

