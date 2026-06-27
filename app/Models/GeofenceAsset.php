<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GeofenceAsset extends Model
{
    //
    use HasUuids;

    protected $fillable = [
        'asset_id',
        'geofence_id',
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
