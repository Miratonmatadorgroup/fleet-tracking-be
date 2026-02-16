<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api.paystack.co';
        $this->secretKey = config('services.paystack.secret_key'); 
    }

    /**
     * Resolve an account number to account name
     */
    public function resolveAccountName(string $accountNumber, string $bankCode): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/bank/resolve", [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        if ($response->failed()) {
            $errorBody = $response->json();
            $message = $errorBody['message'] ?? 'Paystack API request failed';
            return [
                'success' => false,
                'error'   => $message,
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
                'error'   => 'Account name not found. Possible invalid account number or bank code.',
            ];
        }

        return [
            'success'        => true,
            'account_name'   => $data['account_name'],
            'account_number' => $data['account_number'],
            'bank_code'      => $bankCode,
        ];
    }



    public function getBanks(): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/bank");

        if ($response->failed() || empty($response['status'])) {
            return null;
        }

        return $response['data']; // list of banks with name + code
    }
}
