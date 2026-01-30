<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'customer_id',
        'api_key',
        'is_blocked',
        'environment',
        'active',
        'ip_whitelist',
    ];

    protected $casts = [
        'active' => 'boolean',
        'ip_whitelist' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $client) {
            if (empty($client->{$client->getKeyName()})) {
                $client->{$client->getKeyName()} = (string) Str::uuid();
            }

            if (empty($client->api_key)) {
                $client->api_key = self::generateApiKey();
            }
        });
    }

    public static function generateApiKey(): string
    {
        // Example: lpft_Q29L1uPx...
        return 'lpft_' . Str::random(48);
    }


    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
