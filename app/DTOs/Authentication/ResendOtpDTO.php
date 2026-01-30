<?php

namespace App\DTOs\Authentication;

use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class ResendOtpDTO
{
    public string $reference;

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'reference' => 'required|string',
        ]);

        return new self($validated['reference']);
    }

    public function __construct(string $reference)
    {
        $this->reference = $reference;
    }
}
