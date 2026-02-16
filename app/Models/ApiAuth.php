<?php

namespace App\Models;

use App\Enums\ApiAuthTypesEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ApiAuth extends Model
{
    use HasUuids;

    protected $table = 'api_auths';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'api_endpoint_id',
        'type',
        'description',
    ];

    protected $casts = [
        'type' => ApiAuthTypesEnums::class,
    ];

    /**
     * Auth definition belongs to an API endpoint
     */
    public function endpoint()
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id');
    }
}
