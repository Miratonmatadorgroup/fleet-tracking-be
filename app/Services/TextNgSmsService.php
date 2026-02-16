<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TextNgSmsService
{
    protected string $key;
    protected string $sender;
    protected string $route;
    protected string $bypassCode;
    protected string $baseUrl;

    public function __construct()
    {
        $this->key = config('services.mytextng.key');
        $this->sender = config('services.mytextng.sender');
        $this->route = config('services.mytextng.route', '3');
        $this->bypassCode = config('services.mytextng.bypass_code') ?? '';
        $this->baseUrl = config('services.mytextng.base_url', 'https://api.textng.xyz');
    }

    public function sendSms($phone, $message)
    {
        $formattedMessage = str_replace(' ', '|_', $message);

        $payload = [
            'key' => $this->key,
            'sender' => $this->sender,
            'phone' =>  $phone,
            'siscb'      => 1,
            'type'       => 0,
            'message' => $formattedMessage,
            'route' => $this->route,
        ];

        if ($this->bypassCode) {
            $payload['bypasscode'] = $this->bypassCode;
        }

        Log::info('TextNg OTP Request', [
            'phone' => $phone,
            'message' => $message,
            'payload' => $payload
        ]);

        $response = Http::timeout(60)->asForm()->post("{$this->baseUrl}/otp-sms/", $payload);

        // Log response after sending
        Log::info('TextNg OTP Response', [
            'phone' => $phone,
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        return $response->body();
    }
}
