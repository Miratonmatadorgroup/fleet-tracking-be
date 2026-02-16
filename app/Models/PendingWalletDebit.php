<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class PendingWalletDebit extends Model
{
    public $incrementing = false;
    protected $keyType = 'uuid';

    protected $fillable = [
        'id', 'wallet_id', 'amount', 'description', 'method', 'reference',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->id ??= Str::uuid();
        });
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
