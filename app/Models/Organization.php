<?php

namespace App\Models;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasUuids, HasFactory;

    protected $table = 'organizations';

    /**
     * The primary key type is UUID (string)
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'name',
        'type',
        'partner_code',
        'contact_email',
        'contact_phone',
        'address',
        'status',
    ];

    /**
     * Automatically cast attributes
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method to auto-generate UUID
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organization) {
            if (empty($organization->id)) {
                $organization->id = (string) Str::uuid();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Organization has many assets
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    // Organization has many users
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
