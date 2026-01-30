<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextNgService
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
        $this->baseUrl = config('services.mytextng.base_url', 'https://api.textng.xyz');
    }

    public function sendOtp(string $phone, string $message): string
    {
        $formattedMessage = str_replace(' ', '|_', $message);

        $payload = [
            'key' => $this->key,
            'sender' => $this->sender,
            'phone' => $phone,
            'siscb'      => 1,
            'route' => $this->route,
            'message' => $formattedMessage,
        ];

        $response = Http::timeout(60)->asForm()->get("{$this->baseUrl}/sendsms/", $payload);
        if ($response->failed()) {
            Log::error('TextNgService: Failed to send OTP', [
                'phone' => $phone,
                'message' => $message,
                'response' => $response->body(),
            ]);
        }
        return $response->body();
    }
}
