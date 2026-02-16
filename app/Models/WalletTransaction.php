<?php

namespace App\Models;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;


class WalletTransaction extends Model
{
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'id',
        'wallet_id',
        'user_id',
        'type',
        'amount',
        'description',
        'reference',
        'status',
        'method',
    ];

    protected $casts = [
        'status' => WalletTransactionStatusEnums::class,
        'type'   => WalletTransactionTypeEnums::class,
        'method' => WalletTransactionMethodEnums::class,
    ];

    protected static function booted()
    {
        static::creating(function ($transaction) {
            $transaction->id = Str::uuid();
        });
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
