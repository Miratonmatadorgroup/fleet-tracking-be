<?php

namespace App\Events\Authentication;

use App\Models\User;
use Illuminate\Queue\SerializesModels;
use App\DTOs\Authentication\RegisterUserDTO;
use Illuminate\Foundation\Events\Dispatchable;

class UserRegisteredEvent
{
    use Dispatchable, SerializesModels;

    public RegisterUserDTO $dto;
    public string $otp;

    public function __construct(RegisterUserDTO $dto, string $otp)
    {
        $this->dto = $dto;
        $this->otp = $otp;
    }
}
