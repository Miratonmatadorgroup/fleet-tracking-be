<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebhookLog extends Model
{
    //
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'delivery_id',
        'api_client_webhook_id',
        'event',
        'url',
        'response_code',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (
            self $log
        ) {
            if (
                empty($log->{$log->getKeyName()})
            ) {
                $log->{$log->getKeyName()}
                    = (string) Str::uuid();
            }
        });
    }

    public function delivery()
    {
        return $this->belongsTo(
            Delivery::class
        );
    }

    public function webhook()
    {
        return $this->belongsTo(
            ApiClientWebhook::class,
            'api_client_webhook_id'
        );
    }
}
