<?php

namespace App\Models;

use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiClientWebhook extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'api_client_id',
        'webhook_url',
        'webhook_secret',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $webhook) {
            if (empty($webhook->{$webhook->getKeyName()})) {
                $webhook->{$webhook->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function logs()
    {
        return $this->hasMany(
            WebhookLog::class,
            'api_client_webhook_id'
        );
    }
}
