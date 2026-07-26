<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiRequestLog extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'api_client_id',
        'endpoint',
        'is_successful',
        'response_code',
    ];


    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }
}
