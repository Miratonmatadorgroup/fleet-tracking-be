<?php

namespace App\Models;

use App\Models\Tracker;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrackerLocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'imei',
        'latitude',
        'longitude',
        'tracker_id',
        'speed',
        'tracker_time',
        'raw_packet',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'tracker_time' => 'datetime',
        'raw_packet' => 'string',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function tracker()
    {
        return $this->belongsTo(Tracker::class);
    }
}
