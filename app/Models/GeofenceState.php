<?php

namespace App\Models;

use App\Models\Asset;
use App\Models\Geofence;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeofenceState extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'geofence_states';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'asset_id',
        'geofence_id',
        'is_inside',
        'updated_at',
    ];

    protected $casts = [
        'is_inside' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }
}
