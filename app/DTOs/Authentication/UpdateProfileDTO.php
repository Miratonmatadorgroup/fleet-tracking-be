<?php

namespace App\DTOs\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateProfileDTO
{
    public array $validated;
    public $imageFile;

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'             => 'sometimes|string|max:255',
            'email'            => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'            => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'whatsapp_number'  => 'sometimes|string|max:20|unique:users,whatsapp_number,' . $user->id,
            'password'         => 'sometimes|string|min:8',
            'image'            => 'nullable|image|max:5120',

            //banking fields
            'account_number'   => 'sometimes|digits_between:8,12',
            'bank_code'        => 'sometimes|string|max:20',
            'bank_name'        => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new self(
            $validator->validated(),
            $request->file('image')
        );
    }

    public function __construct(array $validated, $imageFile = null)
    {
        $this->validated = $validated;
        $this->imageFile = $imageFile;
    }
}
