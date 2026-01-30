<?php

namespace App\DTOs\Payment;


use Illuminate\Http\Request;

class PayWithWalletDTO
{
    public string $delivery_id;

    public string $pin;

    public static function fromRequest(Request $request): self
    {
        $request->validate([
            'delivery_id' => 'required|uuid',
            'transaction_pin' => 'required|size:4'
        ]);

        $dto = new self();
        $dto->delivery_id = $request->delivery_id;
        $dto->pin = $request->transaction_pin;

        return $dto;
    }
}
