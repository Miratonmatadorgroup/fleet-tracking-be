<?php

namespace App\Models;

use App\Models\User;
use App\Models\Delivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverRating extends Model
{
    use HasUuids;

    protected $table = 'driver_ratings';

    /**
     * UUIDs instead of auto-incrementing IDs.
     */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'delivery_id',
        'ride_pool_id',
        'driver_id',
        'customer_id',
        'rating',
        'comment',
    ];


    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }


    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
