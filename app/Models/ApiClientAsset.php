<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiClientAsset extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [

        'api_client_id',

        'asset_id',

        'tracker_id',

        'imei',

    ];

    protected static function booted()
    {
        static::creating(function ($model) {

            if (!$model->id) {

                $model->id = (string) Str::uuid();

            }

        });
    }

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function tracker()
    {
        return $this->belongsTo(Tracker::class);
    }
}