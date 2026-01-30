<?php
namespace App\DTOs\TransportPricing;
use Illuminate\Http\Request;

class UpdateTransportPricingDTO
{
    public string $mode;
    public float $price_per_km;

    public function __construct(Request $request)
    {
        $this->mode = $request->input('mode');
        $this->price_per_km = $request->input('price_per_km');
    }
}
