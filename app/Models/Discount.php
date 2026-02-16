<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Discount extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'percentage',
        'is_active',
        'applies_to_all',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'discount_user', 'discount_id', 'user_id');
    }
}
