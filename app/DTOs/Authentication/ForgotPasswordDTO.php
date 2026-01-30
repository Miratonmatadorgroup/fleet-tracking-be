<?php
namespace App\DTOs\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ForgotPasswordDTO
{
    public string $identifier;

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self($request->input('identifier'));
    }

    public function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }
}
