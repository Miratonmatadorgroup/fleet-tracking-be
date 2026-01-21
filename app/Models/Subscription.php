<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset_id',
        'plan_class',
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
            'price_per_month' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'trial_end_date' => 'date',
            'is_trial' => 'boolean',
            'auto_renew' => 'boolean',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'active')
                     ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    public function scopeByClass($query, string $class)
    {
        return $query->where('plan_class', $class);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->end_date->isPast();
    }

    public function daysUntilExpiry(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function getTotalAmount(): float
    {
        $months = match($this->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'biannual' => 6,
            'yearly' => 12,
            default => 1,
        };

        return $this->price_per_month * $months;
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function renew(string $billingCycle, bool $autoRenew = true): self
    {
        $months = match($billingCycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'biannual' => 6,
            'yearly' => 12,
            default => 1,
        };

        $this->update([
            'billing_cycle' => $billingCycle,
            'start_date' => now(),
            'end_date' => now()->addMonths($months),
            'status' => 'active',
            'auto_renew' => $autoRenew,
        ]);

        return $this;
    }
}