<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\User;
use Carbon\Carbon;

class DiscountService
{
    public function getUserDiscount(?User $user): ?Discount
    {
        // Global active discount valid
        $global = Discount::where('is_active', true)
            ->where('applies_to_all', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->first();

        if ($global) return $global;

        if (!$user) return null;

        // User-specific discount
        return $user->discounts()
            ->where('discounts.is_active', true) // add table prefix
            ->where(function ($q) {
                $q->whereNull('discounts.expires_at')
                    ->orWhere('discounts.expires_at', '>=', now());
            })
            ->first();
    }
}
