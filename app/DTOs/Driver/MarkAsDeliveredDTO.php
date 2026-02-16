<?php
namespace App\DTOs\Driver;


use Illuminate\Support\Facades\Auth;

class MarkAsDeliveredDTO
{
    public string $deliveryId;
    public string $driverId;

    public function __construct(string $deliveryId)
    {
        $this->deliveryId = $deliveryId;

        $driver = Auth::user()?->driver;
        if (!$driver) {
            throw new \Exception('Driver profile not found.', 404);
        }

        $this->driverId = $driver->id;
    }

    public static function from(string $deliveryId): self
    {
        return new self($deliveryId);
    }
}
