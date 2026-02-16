<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ApiHeader extends Model
{
    use HasUuids;

    protected $table = 'api_headers';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'api_endpoint_id',
        'name',
        'value',
        'is_required',
        'description',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    /**
     * Header belongs to an API endpoint
     */
    public function endpoint()
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id');
    }
}
