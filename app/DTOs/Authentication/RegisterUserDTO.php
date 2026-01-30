<?php

namespace App\DTOs\Authentication;

use Illuminate\Http\Request;

class RegisterUserDTO
{
    public string $name;
    public ?string $email;
    public ?string $phone;
    public ?string $whatsapp_number;
    public string $password;
    public ?string $currency;
    public bool $is_virtual_account;
    public ?string $provider;

    public bool $is_dev;

    public function __construct(array $data)
    {
        $this->name               = $data['name'];
        $this->email              = $data['email'] ?? null;
        $this->phone              = $data['phone'] ?? null;
        $this->whatsapp_number    = $data['whatsapp_number'] ?? null;
        $this->password           = $data['password'];
        $this->currency           = $data['currency'] ?? null;
        $this->is_virtual_account = filter_var($data['is_virtual_account'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->provider           = $data['provider'] ?? null;
        $this->is_dev = ($data['registration_type'] ?? 'user') === 'developer';
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request->only([
            'name',
            'email',
            'phone',
            'whatsapp_number',
            'password',
            'currency',
            'is_virtual_account',
            'provider',
            'registration_type',
        ]));
    }
}
