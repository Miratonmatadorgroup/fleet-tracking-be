<?php

namespace App\Models;

use App\Enums\MerchantStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Merchant extends Model
{
    use HasUuids;

    protected $table = 'merchants';

    protected $fillable = [
        'user_id',
        'merchant_code',
        'status',
        'verified_at',
        'verified_by',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'status' => MerchantStatusEnums::class,
        'verified_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function trackers()
    {
        return $this->hasMany(Tracker::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function approve(User $admin): void
    {
        $this->update([
            'status' => MerchantStatusEnums::APPROVED,
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    public function suspend(?string $reason = null): void
    {
        $this->update([
            'status' => MerchantStatusEnums::SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }
}
