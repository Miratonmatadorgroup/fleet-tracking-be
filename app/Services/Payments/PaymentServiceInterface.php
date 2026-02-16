<?php

namespace App\Services\Payments;

interface PaymentServiceInterface
{
    public function initiate($delivery): array;

    public function verify(string $reference, ?string $deliveryId = null): array;
}
