<?php

namespace App\Models;

use App\Enums\SubscriptionStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset_id',
        'plan_id',
        'billing_cycle',
        'price_per_month',
        'start_date',
        'end_date',
        'status',
        'is_trial',
        'trial_end_date',
        'auto_renew',
        'payment_method',
        'stripe_subscription_id',
        'paystack_subscription_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatusEnums::class,
            'price_per_month' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'trial_end_date' => 'date',
            'is_trial' => 'boolean',
            'auto_renew' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (enum-safe)
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatusEnums::ACTIVE);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', SubscriptionStatusEnums::EXPIRED);
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query
            ->where('status', SubscriptionStatusEnums::ACTIVE)
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatusEnums::ACTIVE
            && $this->end_date->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatusEnums::EXPIRED
            || $this->end_date->isPast();
    }

    public function daysUntilExpiry(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => SubscriptionStatusEnums::EXPIRED,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => SubscriptionStatusEnums::CANCELLED,
            'auto_renew' => false,
        ]);
    }
}
