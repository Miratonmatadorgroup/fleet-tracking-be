<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class NigerianBanksService
{
    protected Client $client;
    protected string $paystackKey;
    protected string $paystackUrl;

    public function __construct()
    {
        // Nigerian Banks API (free)
        $this->client = new Client([
            'base_uri' => config('services.ngbnk.base_url'),
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Paystack for account verification
        $this->paystackKey = config('services.paystack.secret_key');
        $this->paystackUrl = 'https://api.paystack.co';
    }

    /**
     * Fetch list of all Nigerian banks (nigerianbanks.xyz)
     */
    public function getBanks(): ?array
    {
        try {
            $response = $this->client->get('/');
            $data = json_decode($response->getBody(), true);

            return collect($data)
                ->map(fn($item) => [
                    'code' => $item['code'],
                    'name' => $item['name'],
                ])
                ->toArray();

        } catch (\Exception $e) {
            Log::error('Bank list error', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve account name using Paystack
     */
    public function resolveAccountName(string $accountNumber, string $bankCode): array
    {
        $response = Http::withToken($this->paystackKey)
            ->get("{$this->paystackUrl}/bank/resolve", [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        if ($response->failed()) {
            return [
                'success' => false,
                'error'   => $response->json('message') ?? 'Paystack API request failed',
            ];
        }

        if (!$response->json('status')) {
            return [
                'success' => false,
                'error'   => $response->json('message'),
            ];
        }

        $data = $response->json('data');

        if (empty($data['account_name'])) {
            return [
                'success' => false,
                'error'   => 'Account name not found. Possibly invalid account number or bank code.',
            ];
        }

        return [
            'success'        => true,
            'account_name'   => $data['account_name'],
            'account_number' => $data['account_number'],
            'bank_code'      => $bankCode,
        ];
    }
}
