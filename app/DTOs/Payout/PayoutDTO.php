<?php

namespace App\DTOs\Payout;

use App\Models\User;

class PayoutDTO
{
    public function __construct(
        public User $user,
        public float $amount,
        public string $pin

    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user: $data['user'],
            amount: $data['amount'],
            pin: $data['transaction_pin']

        );
    }
}
