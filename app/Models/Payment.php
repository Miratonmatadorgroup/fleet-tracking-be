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
        'delivery_id',
        'api_client_id',
        'currency',
        'user_id',
        'status',
        'reference',
        'amount',
        'gateway',
        'callback_url',
        'meta',
        'original_price',
        'final_price',
        'subsidy_amount',
    ];

    protected $casts = [
        'status' => PaymentStatusEnums::class,
        'meta'   => 'array',
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
}
