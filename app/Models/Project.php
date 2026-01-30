<?php

namespace App\Models;

use App\Models\ApiEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Project extends Model
{
    use HasUuids;

    protected $table = 'projects';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'staging_base_url',
        'live_base_url',
        'created_by',
        'is_public_to_devs',
    ];

    /**
     * A project can have many API endpoints
     */
    public function endpoints()
    {
        return $this->hasMany(ApiEndpoint::class, 'project_id');
    }

    /**
     * The user that created the project
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id');
    }
}
