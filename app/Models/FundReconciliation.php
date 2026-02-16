<?php

namespace App\Models;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Enums\FundsReconcilationStatusEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class FundReconciliation extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'api_client_id',
        'total_amount_owed',
        'paid_amount',
        'balance_owed',
        'received_amount',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => FundsReconcilationStatusEnums::class,
        'total_amount_owed' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_owed' => 'decimal:2',
        'received_amount' => 'decimal:2',
    ];

    /**
     * Boot function from Laravel.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID when creating
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    protected $appends = ['client_name'];

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    public function getClientNameAttribute()
    {
        return $this->apiClient?->name;
    }
}
