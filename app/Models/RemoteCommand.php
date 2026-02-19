<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RemoteCommand extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'remote_commands';

    /**
     * UUID primary key configuration
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'user_id',
        'asset_id',
        'command_type',
        'status',
        'api_response',
        'executed_at',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'api_response' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Boot method for UUID auto-generation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($command) {
            if (empty($command->id)) {
                $command->id = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
