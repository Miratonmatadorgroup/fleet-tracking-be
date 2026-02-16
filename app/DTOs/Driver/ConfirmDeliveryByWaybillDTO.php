<?php
namespace App\DTOs\Driver;

class ConfirmDeliveryByWaybillDTO
{
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $driverId,
        public readonly string $waybillNumber
    ) {}
}
