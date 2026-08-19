<?php

namespace App\DTOs\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VerifyDeleteAccountDTO
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $otp
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'otp'        => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            identifier: $request->identifier,
            otp: $request->otp
        );
    }
}
