<?php

namespace App\DTOs\Authentication;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VerifyOtpDTO
{
    public ?string $identifier = null;
    public ?string $channel    = null;
    public ?string $reference  = null;
    public string $otp;

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'otp'       => 'required|digits:6',
            'reference' => 'sometimes|string',
            'identifier' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $otp = $request->input('otp');

        if ($request->filled('reference')) {
            return new self(
                otp: $otp,
                reference: $request->input('reference'),
            );
        }

        // Otherwise fall back to identifier flow
        $identifier = $request->input('identifier');
        $channel    = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $channel = 'email';
        } else {
            $user = User::where('phone', $identifier)
                ->orWhere('whatsapp_number', $identifier)
                ->first();

            if ($user) {
                if ($user->phone === $identifier) {
                    $channel = 'phone';
                } elseif ($user->whatsapp_number === $identifier) {
                    $channel = 'whatsapp';
                }
            }
        }

        if (!$channel) {
            throw ValidationException::withMessages([
                'identifier' => ['Invalid identifier format or unrecognized contact'],
            ]);
        }

        return new self(
            otp: $otp,
            identifier: $identifier,
            channel: $channel
        );
    }

    public function __construct(
        string $otp,
        ?string $identifier = null,
        ?string $channel = null,
        ?string $reference = null
    ) {
        $this->otp        = $otp;
        $this->identifier = $identifier;
        $this->channel    = $channel;
        $this->reference  = $reference;
    }
}
