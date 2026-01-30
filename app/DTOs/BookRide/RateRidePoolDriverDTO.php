<?php 
namespace App\DTOs\BookRide;

class RateRidePoolDriverDTO
{
    public function __construct(
        public readonly string $ridePoolId,
        public readonly string $customerId,
        public readonly string $driverId,
        public readonly int $rating,
        public readonly ?string $comment = null,
    ) {}
}