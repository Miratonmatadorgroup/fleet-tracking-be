<?php
namespace App\DTOs\Authentication;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResetPasswordDTO
{
    public string $identifier;
    public string $otp;
    public string $password;

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'otp'        => 'required|digits:6',
            'password'   => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            $request->input('identifier'),
            $request->input('otp'),
            $request->input('password')
        );
    }

    public function __construct(string $identifier, string $otp, string $password)
    {
        $this->identifier = $identifier;
        $this->otp = $otp;
        $this->password = $password;
    }
}
