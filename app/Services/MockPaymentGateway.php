<?php

namespace App\Services;

class MockPaymentGateway
{
    public function initialize(array $payload): array
    {
        return [
            'status' => 'success',
            'message' => 'Mock payment initialized successfully',
            'data' => [
                'reference' => uniqid('MOCK-', true),
                'amount' => $payload['amount'],
                'callback_url' => $payload['callback_url'],
                'metadata' => $payload['metadata'],
            ],
        ];
    }

    public function verify(string $reference, ?string $deliveryId = null): array
    {
        // Always return success for "MOCK-" references
        if (str_starts_with($reference, 'MOCK-')) {
            return [
                'status' => true,
                'data' => [
                    'status'   => 'success',
                    'reference' => $reference,
                    'metadata' => [
                        'delivery_id' => $deliveryId,
                    ],
                ],
            ];
        }

        // Otherwise, fail
        return [
            'status' => false,
            'data' => [
                'status'   => 'failed',
                'reference' => $reference,
            ],
        ];
    }
}
