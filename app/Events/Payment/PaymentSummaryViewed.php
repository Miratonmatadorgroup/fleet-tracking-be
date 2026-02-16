<?php

namespace App\Events\Payment;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSummaryViewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ?string $userId,
        public array $summaryData = [] 
    ) {}
}
