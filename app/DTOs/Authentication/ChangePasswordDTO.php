<?php
namespace App\DTOs\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangePasswordDTO
{
    public string $current_password;
    public string $new_password;

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            $request->input('current_password'),
            $request->input('new_password')
        );
    }

    public function __construct(string $current_password, string $new_password)
    {
        $this->current_password = $current_password;
        $this->new_password     = $new_password;
    }
}
