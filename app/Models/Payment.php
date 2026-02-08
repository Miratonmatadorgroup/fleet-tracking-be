<?php

namespace App\Models;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use App\Enums\PaymentStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'subscription_id',
        'user_id',
        'delivery_id',
        'api_client_id',
        'amount',
        'currency',
        'transaction_id',
        'status',
        'reference',
        'gateway',
        'gateway_response',
        'meta',
        'callback_url',
        'paid_at',
    ];

    protected $casts = [
        'status'           => PaymentStatusEnums::class,
        'meta'             => 'array',
        'gateway_response' => 'array',
        'amount'           => 'decimal:2',
        'paid_at'          => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (! $payment->id) {
                $payment->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
