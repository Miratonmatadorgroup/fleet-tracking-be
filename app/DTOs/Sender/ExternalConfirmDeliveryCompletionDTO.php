<?php

namespace App\DTOs\Sender;

class ExternalConfirmDeliveryCompletionDTO
{
    public function __construct(
        public readonly string $trackingNumber 
    ) {}
}
