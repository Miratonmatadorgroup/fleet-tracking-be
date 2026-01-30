<?php

namespace App\Models;

use App\Enums\ApiRequestsTypesEnums;
use Illuminate\Database\Eloquent\Model;

class ApiRequest extends Model
{
    protected $table = 'api_requests';

    protected $fillable = [
        'api_endpoint_id',
        'type',
        'content_type',
        'schema',
    ];

    protected $casts = [
        'type' => ApiRequestsTypesEnums::class,
        'schema' => 'array',
    ];

    /**
     * Request definition belongs to an API endpoint
     */
    public function endpoint()
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id');
    }
}
