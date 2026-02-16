<?php
namespace App\DTOs\Ratings;

class RateDriverDTO
{
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $customerId,
        public readonly string $driverId,
        public readonly int $rating,
        public readonly ?string $comment = null,
    ) {}
}
