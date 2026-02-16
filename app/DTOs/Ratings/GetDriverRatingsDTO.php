<?php
namespace App\DTOs\Ratings;

class GetDriverRatingsDTO
{
    public function __construct(
        public readonly string $driverId,
        public readonly int $perPage = 10, 
    ) {}
}
