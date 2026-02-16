<?php
namespace App\Actions\Investor;

use Carbon\Carbon;
use App\Models\User;
use App\Models\WalletTransaction;
use App\DTOs\Investor\InvestorEarningsDTO;

class GetInvestorEarningsAction
{
    public function execute(User $user): InvestorEarningsDTO
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

        
        $totalTrips = $user->deliveries()
            ->where('status', 'completed')
            ->count();

        return new InvestorEarningsDTO(
            todayEarnings: $todayEarnings,
            weekEarnings: $weekEarnings,
            monthEarnings: $monthEarnings,
            totalEarnings: $totalEarnings,
            totalTrips: $totalTrips
        );
    }
}
