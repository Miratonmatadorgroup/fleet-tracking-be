<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TermiiService
{
    protected string $apiKey;
    protected string $senderId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey   = config('services.termii.api_key');
        $this->senderId = config('services.termii.sender_id');
        $this->baseUrl  = rtrim(config('services.termii.base_url'), '/');
    }

    public function sendSms(string $to, string $message): array
    {
        try {
            // $to = ltrim($to, '+');
            $to = $this->formatPhoneNumber($to);

            $payload = [
                'to'      => $to,
                'from'    => $this->senderId,
                'sms'     => $message,
                'type'    => 'plain',
                'channel' => 'dnd',
                'api_key' => $this->apiKey,
            ];

            Log::info('TERMII PAYLOAD DEBUG', $payload);

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->connectTimeout(10)
                ->post($this->baseUrl . '/api/sms/send', $payload);

            Log::info('Termii SMS response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return $response->json();
        } catch (\Throwable $e) {

            Log::critical('Termii unreachable - OTP NOT SENT', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'status'  => 'failed',
                'message' => 'Termii unreachable',
            ];
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', trim($phone));

        if (str_starts_with($phone, '+234')) {
            return $phone;
        }

        if (str_starts_with($phone, '234')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '0')) {
            return '+234' . substr($phone, 1);
        }

        return '+234' . $phone;
    }
}
