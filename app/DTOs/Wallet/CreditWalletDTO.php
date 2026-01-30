<?php

namespace App\DTOs\Wallet;

use Illuminate\Support\Facades\Log;

class CreditWalletDTO
{
    public string $account_number;
    public float $amount;
    public ?string $description;
    public ?string $method;
    public string $pin;
    public $user;

    public function __construct(array $validated)
    {
        Log::info('DTO RECEIVED DATA', $validated);

        $this->account_number = $validated['account_number'];
        $this->amount = (float) $validated['amount'];
        $this->description = $validated['description'] ?? null;
        $this->method = $validated['method'] ?? null;

        Log::info('PIN BEFORE ASSIGN', ['transaction_pin' => $validated['transaction_pin'] ?? 'MISSING']);

        $this->pin = $validated['transaction_pin'] ?? '';
    }
}
