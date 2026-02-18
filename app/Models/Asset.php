<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'driver_id',
        'equipment_id',
        'asset_type',
        'class',
        'make',
        'model',
        'year',
        'license_plate',
        'vin',
        'color',
        'image_url',
        'driver_image_url',
        'status',
        'base_consumption_rate',
        'idle_consumption_rate',
        'speeding_penalty',
        'last_known_lat',
        'last_known_lng',
        'last_ping_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'base_consumption_rate' => 'decimal:2',
            'idle_consumption_rate' => 'decimal:2',
            'speeding_penalty' => 'decimal:2',
            'last_known_lat' => 'decimal:8',
            'last_known_lng' => 'decimal:8',
            'last_ping_at' => 'datetime',
        ];
    }

    public function tracker()
    {
        return $this->belongsTo(Tracker::class);
    }


    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function gpsLogs()
    {
        return $this->hasMany(GpsLog::class);
    }

    public function fuelReports()
    {
        return $this->hasMany(FuelReport::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    public function geofenceBreaches()
    {
        return $this->hasMany(GeofenceBreach::class);
    }

    public function remoteCommands()
    {
        return $this->hasMany(RemoteCommand::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByClass($query, string $class)
    {
        return $query->where('class', $class);
    }

    public function scopeByOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOnline($query)
    {
        return $query->where('last_ping_at', '>=', now()->subMinutes(5));
    }

    // Helper methods
    public function isOnline(): bool
    {
        return $this->last_ping_at && $this->last_ping_at->gt(now()->subMinutes(5));
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function getLastKnownLocation(): ?array
    {
        if (!$this->last_known_lat || !$this->last_known_lng) {
            return null;
        }

        return [
            'latitude' => (float) $this->last_known_lat,
            'longitude' => (float) $this->last_known_lng,
            'timestamp' => $this->last_ping_at?->toIso8601String(),
        ];
    }
}
