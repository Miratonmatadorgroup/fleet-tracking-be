<?php
namespace App\Events\Driver;


class DriverEarningsFetched
{
    public function __construct(
        public readonly string $driverId,
        public readonly array $earnings
    ) {}
}
