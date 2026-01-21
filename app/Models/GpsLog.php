<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'latitude',
        'longitude',
        'speed',
        'ignition',
        'heading',
        'altitude',
        'satellites',
        'hdop',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'speed' => 'decimal:2',
            'ignition' => 'boolean',
            'heading' => 'decimal:2',
            'altitude' => 'decimal:2',
            'satellites' => 'integer',
            'hdop' => 'decimal:2',
            'timestamp' => 'datetime',
        ];
    }

    public $timestamps = false;

    // Relationships
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    // Scopes
    public function scopeForAsset($query, int $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    public function scopeMoving($query)
    {
        return $query->where('speed', '>', 0);
    }

    public function scopeIdle($query)
    {
        return $query->where('speed', 0)->where('ignition', true);
    }

    public function scopeSpeeding($query, float $threshold = 100)
    {
        return $query->where('speed', '>', $threshold);
    }

    // Helper methods
    public function isMoving(): bool
    {
        return $this->speed > 0;
    }

    public function isIdle(): bool
    {
        return $this->speed == 0 && $this->ignition;
    }

    public function isSpeeding(float $threshold = 100): bool
    {
        return $this->speed > $threshold;
    }
}