<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportPricing extends Model
{
    protected $fillable = [
       'mode_of_transportation',
        'pickup_location',
        'dropoff_location',
        'rate_per_kg',
        'rate_per_route',
    ];
}
