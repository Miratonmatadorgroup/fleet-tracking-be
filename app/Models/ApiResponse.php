<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ApiResponse extends Model
{
    use HasUuids;

    protected $table = 'api_responses';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'api_endpoint_id',
        'status_code',
        'description',
        'body',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'body' => 'array',
    ];

    /**
     * Response belongs to an API endpoint
     */
    public function endpoint()
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id');
    }
}
