<?php

namespace App\Actions\Partner;

use Carbon\Carbon;
use App\Models\User;
use App\Models\WalletTransaction;
use App\DTOs\Partner\PartnerEarningsDTO;

class GetPartnerEarningsAction
{
    public function execute(User $user): PartnerEarningsDTO
    {
        $today  = Carbon::today();
        $week   = Carbon::now()->startOfWeek();
        $month  = Carbon::now()->startOfMonth();

        $query = WalletTransaction::where('user_id', $user->id)
            ->where('type', 'credit');

        $todayEarnings = (clone $query)->whereDate('created_at', $today)->sum('amount');
        $weekEarnings  = (clone $query)->whereBetween('created_at', [$week, Carbon::now()])->sum('amount');
        $monthEarnings = (clone $query)->whereBetween('created_at', [$month, Carbon::now()])->sum('amount');
        $totalEarnings = (clone $query)->sum('amount');

        $totalTrips = $user->partner?->transportModes()
            ->withCount(['deliveries as completed_trips' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get()
            ->sum('completed_trips');

        return new PartnerEarningsDTO(
            todayEarnings: $todayEarnings,
            weekEarnings: $weekEarnings,
            monthEarnings: $monthEarnings,
            totalEarnings: $totalEarnings,
            totalTrips: $totalTrips
        );
    }
}
