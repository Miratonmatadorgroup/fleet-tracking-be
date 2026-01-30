<?php

namespace App\Services\Payments;

use App\Services\Payments\ShanonoPayService;
use App\Services\Payments\PaymentServiceInterface;

class PaymentGatewayFactory
{
    public static function make(string $gateway): PaymentServiceInterface
    {
        return match (strtolower($gateway)) {
            'shanono' => new ShanonoPayService(),
            // Add other providers
            default => throw new \Exception("Unsupported payment gateway: $gateway"),
        };
    }
}
