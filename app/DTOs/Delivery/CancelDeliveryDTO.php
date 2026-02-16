<?php
namespace App\DTOs\Delivery;


class CancelDeliveryDTO
{
    public string $deliveryId;

    public function __construct(string $deliveryId)
    {
        $this->deliveryId = $deliveryId;
    }

    public static function fromRequest(string $uuid): self
    {
        return new self($uuid);
    }
}
