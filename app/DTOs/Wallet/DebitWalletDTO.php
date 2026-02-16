<?php

namespace App\DTOs\Wallet;

class DebitWalletDTO
{
    public string $account_number;
    public float $amount;
    public ?string $description;
    public ?string $method;
    public string $pin;
    public $user;

    public function __construct(array $validated)
    {
        $this->account_number = $validated['account_number'];
        $this->amount = (float) $validated['amount'];
        $this->description = $validated['description'] ?? null;
        $this->method = $validated['method'] ?? null;
        $this->pin = $validated['transaction_pin'];
    }
}
