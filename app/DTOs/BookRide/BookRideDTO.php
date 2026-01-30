<?php

namespace App\DTOs\BookRide;

use App\Models\User;

class BookRideDTO
{
    public string $driver_id;
    public string $transport_mode_id;
    public ?string $estimate_token;
    public string $user_id;
    public User $user;

    public static function fromArray(array $data): self
    {
        $dto = new self;

        $dto->driver_id          = $data['driver_id'];
        $dto->transport_mode_id  = $data['transport_mode_id'];
        $dto->estimate_token     = $data['estimate_token'] ?? null;
        $dto->user_id            = $data['user_id'];
        $dto->user               = User::findOrFail($data['user_id']);

        return $dto;
    }
}

