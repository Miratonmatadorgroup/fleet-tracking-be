<?php

namespace App\DTOs\Authentication;

use Illuminate\Http\Request;

class RegisterUserDTO
{
    public string $name;
    public string $email;
    public string $password;
    public ?string $dob;           // New
    public ?string $gender; 

    public string $user_type;     // individual_operator | business_operator
    public ?string $business_type;
    public ?string $cac_number;
    public ?string $nin_number;

    public ?string $cac_document;

    public bool $kyb_verified = false;



    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->password = $data['password'];
        $this->dob     = $data['dob'] ?? null;
        $this->gender  = $data['gender'] ?? null;

        $this->user_type = $data['operator_type'] === 'business'
            ? 'business_operator'
            : 'individual_operator';

        $this->business_type = $data['business_type'] ?? null;
        $this->cac_number    = $data['cac_number'] ?? null;
        $this->nin_number    = $data['owner_nin'] ?? null;
        $this->cac_document = $data['cac_document'] ?? null;
        $this->kyb_verified = false;
    }

    public static function fromRequest(Request $request): self
    {
        return new self([
            ...$request->only([
                'name',
                'email',
                'password',
                'operator_type',
                'business_type',
                'cac_number',
                'owner_nin',
                'dob',
                'gender',
            ]),
            'cac_document' => $request->get('cac_document_path'),
        ]);
    }
}
