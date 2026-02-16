<?php

namespace App\Models;

use App\Models\User;
use App\Models\TransportMode;
use App\Enums\PaymentMethodEnums;
use App\Enums\InvestorStatusEnums;
use Illuminate\Database\Eloquent\Model;
use App\Enums\InvestorWithdrawalStatusEnums;
use App\Enums\InvestorApplicationStatusEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investor extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'whatsapp_number',
        'business_name',
        'address',
        'gender',
        'bank_name',
        'bank_code',
        'account_name',
        'account_number',
        'next_of_kin_name',
        'next_of_kin_phone',
        'payment_method',
        'investment_amount',
        'status',
        'application_status',
        'withdraw_status',
        'withdrawn_at',
        'refunded_at',
        'refund_note'
    ];

    protected $casts = [
        'status' => InvestorStatusEnums::class,
        'application_status' => InvestorApplicationStatusEnums::class,
        'payment_method' => PaymentMethodEnums::class,
        'withdraw_status' => InvestorWithdrawalStatusEnums::class,
        'withdrawn_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transportModes(): HasMany
    {
        return $this->hasMany(TransportMode::class);
    }
}
