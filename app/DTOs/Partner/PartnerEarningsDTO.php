<?php
namespace App\DTOs\Partner;

class PartnerEarningsDTO
{
    public function __construct(
        public float $todayEarnings,
        public float $weekEarnings,
        public float $monthEarnings,
        public float $totalEarnings,
        public int $totalTrips,
    ) {}

    public function toArray(): array
    {
        return [
            'today_earnings' => $this->todayEarnings,
            'week_earnings'  => $this->weekEarnings,
            'month_earnings' => $this->monthEarnings,
            'total_earnings' => $this->totalEarnings,
            'total_trips'    => $this->totalTrips,
        ];
    }
}
