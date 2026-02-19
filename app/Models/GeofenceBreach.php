<?php

namespace App\Models;

use App\Models\Geofence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeofenceBreach extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'geofence_breaches';

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
        'geofence_id',
        'breach_type',
        'latitude',
        'longitude',
        'timestamp',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'timestamp' => 'datetime',
    ];

    /**
     * Boot method for UUID auto-generation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($geofenceBreach) {
            if (empty($geofenceBreach->id)) {
                $geofenceBreach->id = (string) Str::uuid();
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

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }
}
