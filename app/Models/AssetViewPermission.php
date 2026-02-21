<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AssetViewPermission extends Model
{
    use HasUuids;

    protected $table = 'asset_view_permissions';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'owner_id',
        'viewer_id',
    ];

    /**
     * Owner of the asset
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Viewer who has permission
     */
    public function viewer()
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }
}
