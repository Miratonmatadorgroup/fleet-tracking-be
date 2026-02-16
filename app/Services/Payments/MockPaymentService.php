<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class MockPaymentService implements PaymentServiceInterface
{
    public function initiate($delivery): array
    {
        $mockReference = 'MOCK-' . strtoupper(Str::random(8));

        Cache::put('mock_payment_' . $mockReference, [
            'delivery_id' => $delivery->id,
        ], now()->addMinutes(10));

        $authorizationUrl = route('payment.verify', [
            'reference'   => $mockReference,
            'delivery_id' => $delivery->id,
        ]);

        return [
            'status'  => true,
            'message' => 'Mock payment initialized successfully',
            'data'    => [
                'reference'         => $mockReference,
                'authorization_url' => $authorizationUrl,
                'metadata'          => ['delivery_id' => $delivery->id],
            ],
        ];
    }

    public function verify(string $reference, ?string $deliveryId = null): array
    {
        $metadata = Cache::get('mock_payment_' . $reference, []);

        if (str_contains($reference, 'FAIL')) {
            return [
                'status'  => false,
                'message' => 'Mock verification failed',
                'data'    => [
                    'status'    => 'failed',
                    'reference' => $reference,
                    'metadata'  => $metadata,
                ],
            ];
        }

        return [
            'status'  => true,
            'message' => 'Mock verification successful',
            'data'    => [
                'status'    => 'success',
                'reference' => $reference,
                'metadata'  => $metadata,
            ],
        ];
    }
}
