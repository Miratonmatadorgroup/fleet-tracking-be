<?php
namespace App\DTOs\Driver;


use App\Models\User;
use App\Models\Delivery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ShowDirectionsDTO
{
    public string $pickupLocation;
    public ?float $pickupLat;
    public ?float $pickupLng;
    public User $user;
    public ?Delivery $delivery;

    public static function fromRequest(array $data): self
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('driver')) {
            throw ValidationException::withMessages([
                'authorization' => ['Unauthorized. You must be logged in as a driver.']
            ]);
        }

        // Must provide either delivery_id or pickup_address
        if (empty($data['delivery_id']) && empty($data['pickup_location'])) {
            throw ValidationException::withMessages([
                'input' => ['Either delivery_id or pickup_address is required.']
            ]);
        }

        $delivery = null;
        $pickupLocation = $data['pickup_location'] ?? '';
        $pickupLat = null;
        $pickupLng = null;

        if (!empty($data['delivery_id'])) {
            $delivery = Delivery::findOrFail($data['delivery_id']);
            $pickupLocation = $delivery->pickup_location;
            $pickupLat = $delivery->pickup_latitude;
            $pickupLng = $delivery->pickup_longitude;
        }

        return new self($pickupLocation, $pickupLat, $pickupLng, $user, $delivery);
    }

    public function __construct(
        string $pickupLocation,
        ?float $pickupLat,
        ?float $pickupLng,
        User $user,
        ?Delivery $delivery = null
    ) {
        $this->pickupLocation = $pickupLocation;
        $this->pickupLat = $pickupLat;
        $this->pickupLng = $pickupLng;
        $this->user = $user;
        $this->delivery = $delivery;
    }
}
