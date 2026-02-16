<?php
namespace App\DTOs\Driver;


use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AcceptDeliveryDTO
{
    public string $deliveryId;
    public \App\Models\User $user;

    public static function fromId(string $deliveryId): self
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('driver')) {
            throw ValidationException::withMessages([
                'authorization' => ['Unauthorized. You must be logged in as a driver.']
            ]);
        }

        return new self($deliveryId, $user);
    }

    public function __construct(string $deliveryId, \App\Models\User $user)
    {
        $this->deliveryId = $deliveryId;
        $this->user = $user;
    }
}
