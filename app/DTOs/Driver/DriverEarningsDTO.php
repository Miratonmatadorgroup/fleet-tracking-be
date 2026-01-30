<?php

namespace App\DTOs\Driver;


class DriverEarningsDTO
{
    public function __construct(
        public readonly string $driverId,
        public readonly float $commissionPercentage,
        public readonly ?float $totalEarnings = null,
    ) {}
}
