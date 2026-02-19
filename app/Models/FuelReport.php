<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FuelReport extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'fuel_reports';

    /**
     * UUID primary key configuration
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'asset_id',
        'trip_start',
        'trip_end',
        'distance_km',
        'idle_hours',
        'speeding_km',
        'base_fuel',
        'idle_fuel',
        'speeding_fuel',
        'fuel_consumed_liters',
        'avg_speed',
        'max_speed',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'trip_start' => 'datetime',
        'trip_end' => 'datetime',
        'distance_km' => 'decimal:2',
        'idle_hours' => 'decimal:2',
        'speeding_km' => 'decimal:2',
        'base_fuel' => 'decimal:2',
        'idle_fuel' => 'decimal:2',
        'speeding_fuel' => 'decimal:2',
        'fuel_consumed_liters' => 'decimal:2',
        'avg_speed' => 'decimal:2',
        'max_speed' => 'decimal:2',
    ];

    /**
     * Boot method for UUID auto-generation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($fuelReport) {
            if (empty($fuelReport->id)) {
                $fuelReport->id = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
