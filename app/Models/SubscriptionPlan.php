<?php

namespace App\Models;

use App\Enums\UserTypesEnums;
use App\Enums\BillingCycleEnums;
use App\Enums\SubscriptionFeatureEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubscriptionPlan extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'user_type',
        'billing_cycle',
        'price',
        'features',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'user_type'     => UserTypesEnums::class,
        'billing_cycle' => BillingCycleEnums::class,
        'features'      => 'array',
        'is_active'     => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
}
