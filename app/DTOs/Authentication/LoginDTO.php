<?php
namespace App\DTOs\Authentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginDTO
{
    public string $identifier;
    public string $password;

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            $request->input('identifier'),
            $request->input('password')
        );
    }

    public function __construct(string $identifier, string $password)
    {
        $this->identifier = $identifier;
        $this->password = $password;
    }
}
