<?php

namespace App\DTO\Auth;

use Illuminate\Http\Request;

class DeveloperRegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone,
        public string $company_name,
        public ?string $company_website,
        public ?string $callback_url,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->name,
            email: strtolower(trim($request->email)),
            password: $request->password,
            phone: $request->phone,
            company_name: $request->company_name,
            company_website: $request->company_website,
            callback_url: $request->callback_url,
        );
    }
}