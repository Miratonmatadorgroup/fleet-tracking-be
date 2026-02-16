<?php

namespace App\Models;

use App\Models\ApiAuth;
use App\Models\Project;
use App\Models\ApiHeader;
use App\Models\ApiRequest;
use App\Models\ApiResponse;
use App\Enums\ApiEndpointsMethodEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ApiEndpoint extends Model
{
    use HasUuids;

    protected $table = 'api_endpoints';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'method',
        'path',
        'full_url',
        'version',
        'is_active',
    ];

    protected $casts = [
        'method' => ApiEndpointsMethodEnums::class,
        'is_active' => 'boolean',
    ];

    /**
     * Endpoint belongs to a project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requests()
    {
        return $this->hasMany(ApiRequest::class);
    }

    public function headers()
    {
        return $this->hasMany(ApiHeader::class);
    }

    public function responses()
    {
        return $this->hasMany(ApiResponse::class);
    }

    public function auth()
    {
        return $this->hasOne(ApiAuth::class);
    }
}
