<?php

namespace App\DTOs\Payment;

class PaymentSummaryDTO
{
    public function __construct(
        public float $totalCollected,
        public float $deliveryTotal,
        public float $deliveryRevenue,
        public float $totalOriginal,
        public float $totalSubsidy,
        public float $investmentTotal,
        public array $deliveryBreakdown,
        public array $investmentBreakdown
    ) {}
}
