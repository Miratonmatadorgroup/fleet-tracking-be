<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportModePricing extends Model
{

    protected $fillable = [
        'mode',           // e.g., 'bike', 'van', 'truck'
        'price_per_km',   // numeric price per kilometer
    ];


}
