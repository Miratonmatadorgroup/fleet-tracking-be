<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Geofence extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'geofences';

    /**
     * UUID primary key configuration
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'coordinates',
        'radius_meters',
        'is_active',
        'alert_on_entry',
        'alert_on_exit',
        'curfew_start',
        'curfew_end',
        'geometry',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'coordinates' => 'array',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
        'alert_on_entry' => 'boolean',
        'alert_on_exit' => 'boolean',
        'curfew_start' => 'datetime:H:i',
        'curfew_end' => 'datetime:H:i',
    ];

    /**
     * Boot method for UUID auto-generation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($geofence) {
            if (empty($geofence->id)) {
                $geofence->id = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function breaches()
    {
        return $this->hasMany(GeofenceBreach::class, 'geofence_id');
    }
}
