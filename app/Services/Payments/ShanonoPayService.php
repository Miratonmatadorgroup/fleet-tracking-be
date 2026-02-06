<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\Payments\PaymentServiceInterface;


class ShanonoPayService implements PaymentServiceInterface
{
    protected string $baseUrl;
    protected string $publicKey;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = config('services.shanono.base_url');
        $this->publicKey = config('services.shanono.public');
        $this->secretKey = config('services.shanono.secret');
    }

    /**
     * Initiate a payment request with Shanono
     */

    public function initiate($delivery): array
    {
        try {
            $reference = 'SHPG' . uniqid() . rand(1000, 9999);

            $callbackUrl = route('payments.callback', [
                'delivery_id' => $delivery->id,
                'reference'   => $reference,
            ]);
            $webhookUrl = route('payments.webhook', [
                'delivery_id' => $delivery->id,
                'reference'   => $reference,
            ]);

            $names = explode(' ', $delivery->sender_name ?? 'Customer User', 2);
            $firstName = $names[0] ?? 'Customer';
            $lastName  = $names[1] ?? 'User';

            $payload = [
                'firstName'   => $firstName,
                'lastName'    => $lastName,
                'email'       => $delivery->customer->email ?? 'admin@useLoopFreight.com',
                'mobile'      => $delivery->customer->phone ?? '08000000000',
                'country'     => 'NG',
                'currency'    => 'NGN',
                'amount'      => (string) round($delivery->total_price, 2),
                'reference'   => $reference,
                'description' => "Payment for Delivery {$delivery->id}",
                'apiKey'      => $this->publicKey,
                'callbackUrl' => $callbackUrl,
                'webhookUrl'  => $webhookUrl,
            ];

            Log::info('Shanono initiate request', ['payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type'  => 'application/json',
            ])->post("{$this->baseUrl}/merchant/initiate-payment", $payload);

            $json = $response->json();

            Log::info('Shanono initiate response', [
                'status' => $response->status(),
                'body'   => $json,
            ]);

            if (!$response->successful()) {
                return [
                    'status'  => false,
                    'message' => data_get($json, 'message', 'Failed to initiate payment'),
                    'raw'     => $json,
                ];
            }


            return [
                'status'    => true,
                'message'   => data_get($json, 'message', 'Payment request successful'),
                'reference' => $reference,
                'payment'   => data_get($json, 'data.data.payment')
                    ?? data_get($json, 'data.payment'),
                'verify_url' => route('payment.verify', [
                    'reference'   => $reference,
                    'delivery_id' => $delivery->id,
                ]),
                'callback_url' => $callbackUrl,
                'webhook_url'  => $webhookUrl,
                'raw'          => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('Shanono initiate exception', [
                'error'       => $e->getMessage(),
                'delivery_id' => $delivery->id,
            ]);

            return [
                'status'  => false,
                'message' => 'Unexpected error during payment initiation',
                'error'   => $e->getMessage(),
            ];
        }
    }


    /**
     * Verify transaction status with Shanono
     */

    public function verify(string $reference, ?string $deliveryId = null): array
    {
        try {
            $driver = DB::getDriverName();
            $referenceColumn = $driver === 'pgsql'
                ? "meta->>'gateway_reference'"
                : "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gateway_reference'))";

            //Unified query for both MySQL and PostgreSQL
            $payment = Payment::where('reference', $reference)
                ->orWhere('delivery_id', $deliveryId)
                ->orWhereRaw("$referenceColumn = ?", [$reference])
                ->first();


            if (!$payment) {
                Log::warning("Shanono verify: Payment not found", [
                    'reference'   => $reference,
                    'delivery_id' => $deliveryId,
                ]);

                return [
                    'status'  => false,
                    'message' => 'Payment not found',
                    'data'    => [],
                    'raw'     => [],
                ];
            }

            $meta = $payment->meta;
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            $meta = is_array($meta) ? $meta : [];

            $gatewayRef = data_get($meta, 'gateway_reference');
            if ($gatewayRef && str_starts_with($gatewayRef, 'TXN_')) {
                $reference = $gatewayRef;
            }

            $payload   = ['reference' => $reference];
            $secretKey = config('services.shanono.secret');
            $url       = "{$this->baseUrl}/checkout/verify-payment";

            Log::info('Shanono verify request', [
                'url'          => $url,
                'payload'      => $payload,
                'delivery_id'  => $deliveryId,
                'reference'    => $reference,
                'headers'      => [
                    'Authorization' => 'Bearer *****' . substr($secretKey, -6),
                    'Content-Type'  => 'application/json',
                ],
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$secretKey}",
                'Content-Type'  => 'application/json',
            ])->post($url, $payload);

            if ($response->failed()) {
                Log::error('Shanono verify failed response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'url'    => $url,
                    'payload' => $payload,
                    'secret_key_ending' => substr($secretKey, -6),
                ]);

                return [
                    'status'  => false,
                    'message' => 'Shanono verification failed',
                    'data'    => [],
                    'raw'     => [
                        'shanono_status' => $response->status(),
                        'shanono_body'   => json_decode($response->body(), true) ?? $response->body(),
                        'payload_sent'   => $payload,
                        'url'            => $url,
                    ],
                ];
            }

            $json = $response->json() ?? [];

            Log::info('Shanono verify response', [
                'status' => $response->status(),
                'body'   => $json,
            ]);

            // Normalize response data
            $data          = data_get($json, 'data', []);
            $message       = data_get($json, 'message', 'No message');
            $globalStatus = data_get($json, 'success', false);

            $rawDataStatus = data_get($data, 'status');

            $dataStatus = is_string($rawDataStatus)
                ? strtolower($rawDataStatus)
                : (is_bool($rawDataStatus) ? ($rawDataStatus ? 'true' : 'false') : (string) $rawDataStatus);

            $successStatuses = [
                'success',
                'successful',
                'paid',
                'true',
                'approved',
                'completed',
                'settled'
            ];

            $isSuccess = ($globalStatus === true) && ($dataStatus === 'successful');

            Log::info('Shanono verify normalized result', [
                'reference'    => $reference,
                'delivery_id'  => $deliveryId,
                'globalStatus' => $globalStatus,
                'dataStatus'   => $dataStatus,
                'isSuccess'    => $isSuccess,
            ]);

            return [
                'status'  => $isSuccess,
                'message' => $message,
                'data'    => $data,
                'raw'     => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('Shanono verify exception', [
                'reference'   => $reference,
                'delivery_id' => $deliveryId,
                'error'       => $e->getMessage(),
            ]);

            return [
                'status'  => false,
                'message' => $e->getMessage(),
                'data'    => [],
                'raw'     => [],
            ];
        }
    }
}
