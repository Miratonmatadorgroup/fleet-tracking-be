<?php

namespace App\Models;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverLocation extends Model
{
    use HasUuids;
    protected $table = 'driver_locations';
    protected $fillable = [
        'driver_id',
        'delivery_id',
        'latitude',
        'longitude',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
