<?php

namespace App\Models;

use Illuminate\Support\Str;
use App\Enums\CurrencyEnums;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'id',
        'user_id',
        'pending_balance',
        'available_balance',
        'total_balance',
        'account_number',
        'currency',
        'bank_name',
        'is_virtual_account',
        'provider',
        'external_account_id',
        'external_account_number',
        'external_account_name',
        'external_bank',
        'external_reference',
        'external_available_balance',
        'external_book_balance',
    ];

    protected $casts = [
        'currency' => CurrencyEnums::class,
    ];

    protected static function booted()
    {
        static::creating(function ($wallet) {
            $wallet->id = Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
