<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Token;


class UserToken extends Model
{
    protected $table = 'user_tokens';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'device_name',
        'ip_address',
        'user_agent',
        'last_activity',
        'expires_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
