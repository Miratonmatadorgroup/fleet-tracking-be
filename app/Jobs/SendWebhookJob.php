<?php

namespace App\Jobs;

use App\Models\ApiClient;
use App\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ApiClient $client,
        public string $event,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhook = $this->client->webhook;

        if (
            ! $webhook ||
            ! $webhook->is_active ||
            empty($webhook->webhook_url)
        ) {
            return;
        }

        $body = [

            'event' => $this->event,

            'payload' => $this->payload,

            'timestamp' => now()->toIso8601String(),

        ];

        $signature = hash_hmac(
            'sha256',
            json_encode($body),
            $webhook->webhook_secret
        );

        try {

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Signature' => $signature,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    $webhook->webhook_url,
                    $body
                );

            WebhookLog::create([

                'api_client_webhook_id' => $webhook->id,

                'event' => $this->event,

                'url' => $webhook->webhook_url,

                'response_code' => $response->status(),

                'payload' => $body,

            ]);
        } catch (\Throwable $e) {

            Log::error('Webhook delivery failed', [

                'client' => $this->client->id,

                'event' => $this->event,

                'error' => $e->getMessage(),

            ]);

            WebhookLog::create([

                'api_client_webhook_id' => $webhook->id,

                'event' => $this->event,

                'url' => $webhook->webhook_url,

                'response_code' => null,

                'payload' => $body,

            ]);
        }
    }
}
