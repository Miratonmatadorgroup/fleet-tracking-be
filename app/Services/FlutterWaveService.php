<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterWaveService
{
    protected string $flutterwaveKey;
    protected string $flutterwaveUrl;

    public function __construct()
    {
        $this->flutterwaveKey = config('services.flutterwave.secret_key');
        $this->flutterwaveUrl = 'https://api.flutterwave.com/v3';
    }

    /**
     * Fetch list of Nigerian banks (Flutterwave)
     */
    public function getBanks(): ?array
    {
        try {
            $response = Http::withToken($this->flutterwaveKey)
                ->get("{$this->flutterwaveUrl}/banks/NG");

            if ($response->failed() || !$response->json('status')) {
                Log::error('Flutterwave bank list error', [
                    'response' => $response->json(),
                ]);
                return null;
            }

            return collect($response->json('data'))
                ->map(fn($bank) => [
                    'code' => $bank['code'],
                    'name' => $bank['name'],
                ])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Flutterwave bank list exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve account name using Flutterwave
     */

    public function resolveAccountName(string $accountNumber, string $bankCode): array
    {
        try {
            // Normalize bank code
            $numericBankCode = (int) $bankCode;

            // Check for sandbox environment
            $isSandbox = str_contains($this->flutterwaveKey, 'FLWPUBK_TEST');
            if ($isSandbox && $numericBankCode !== 44) {
                return [
                    'success' => false,
                    'error'   => 'Sandbox mode only allows bank code 044 (Access Bank).',
                ];
            }

            // Make Flutterwave API request
            $response = Http::withToken($this->flutterwaveKey)
                ->post("{$this->flutterwaveUrl}/accounts/resolve", [
                    'account_number' => $accountNumber,
                    'account_bank'   => $numericBankCode,
                ]);

            // Log everything
            Log::info('Flutterwave resolve response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            // If request failed
            if ($response->failed()) {
                return [
                    'success' => false,
                    'error'   => $response->json('message') ?? 'Flutterwave request failed.',
                ];
            }

            $data = $response->json('data');

            // Check if Flutterwave returned an invalid structure
            if (! $data || ! isset($data['account_name'])) {
                return [
                    'success' => false,
                    'error'   => 'Unable to resolve account name.',
                ];
            }

            // Return success
            return [
                'success'       => true,
                'account_name'  => $data['account_name'],
                'account_number' => $data['account_number'] ?? $accountNumber,
                'bank_code'     => $numericBankCode,
            ];
        } catch (\Throwable $e) {
            Log::error('Flutterwave resolve error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error'   => 'An unexpected error occurred while resolving account name.',
            ];
        }
    }
}
