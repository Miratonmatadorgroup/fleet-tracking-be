<?php

namespace App\Actions\Driver;

use Carbon\Carbon;
use App\Models\Delivery;
use App\Models\RidePool;
use App\DTOs\Driver\DriverEarningsDTO;

class GetDriverEarningsAction
{
    public function execute(DriverEarningsDTO $dto): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        // Only include completed deliveries for the driver
        $query = Delivery::query()
            ->where('driver_id', $dto->driverId)
            ->where('status', 'completed');

        $todayTotal = (clone $query)
            ->whereDate('updated_at', $today)
            ->sum('total_price');

        $weekTotal = (clone $query)
            ->whereBetween('updated_at', [$weekStart, $weekEnd])
            ->sum('total_price');

        $monthTotal = (clone $query)
            ->whereBetween('updated_at', [$monthStart, $monthEnd])
            ->sum('total_price');

        $allTimeTotal = (clone $query)->sum('total_price');

        $totalTrips = (clone $query)->count();

        // Apply driver commission %
        $todayEarnings = ($dto->commissionPercentage / 100) * $todayTotal;
        $weekEarnings  = ($dto->commissionPercentage / 100) * $weekTotal;
        $monthEarnings = ($dto->commissionPercentage / 100) * $monthTotal;
        $totalEarnings = ($dto->commissionPercentage / 100) * $allTimeTotal;

      // Ride Pool earnings
        $rideQuery = RidePool::query()
            ->where('driver_id', $dto->driverId)
            ->where('status', 'ride_ended')
            ->where('payment_status', 'paid');

        $rideToday = (clone $rideQuery)->whereDate('updated_at', $today)->sum('estimated_cost');
        $rideWeek = (clone $rideQuery)->whereBetween('updated_at', [$weekStart, $weekEnd])->sum('estimated_cost');
        $rideMonth = (clone $rideQuery)->whereBetween('updated_at', [$monthStart, $monthEnd])->sum('estimated_cost');
        $rideAllTime = (clone $rideQuery)->sum('estimated_cost');
        $totalRides = (clone $rideQuery)->count();

        // Commission
        $rideTodayEarnings  = ($dto->commissionPercentage / 100) * $rideToday;
        $rideWeekEarnings   = ($dto->commissionPercentage / 100) * $rideWeek;
        $rideMonthEarnings  = ($dto->commissionPercentage / 100) * $rideMonth;
        $rideTotalEarnings  = ($dto->commissionPercentage / 100) * $rideAllTime;


        return [
            'today_earnings' => round($todayEarnings + $rideTodayEarnings, 2),
            'week_earnings'  => round($weekEarnings + $rideWeekEarnings, 2),
            'month_earnings' => round($monthEarnings + $rideMonthEarnings, 2),
            'total_earnings' => round($totalEarnings + $rideTotalEarnings, 2),

            'delivery_earnings' => [
                'today' => round($todayEarnings, 2),
                'week'  => round($weekEarnings, 2),
                'month' => round($monthEarnings, 2),
                'total' => round($totalEarnings, 2),
            ],

            'ride_pool_earnings' => [
                'today' => round($rideTodayEarnings, 2),
                'week'  => round($rideWeekEarnings, 2),
                'month' => round($rideMonthEarnings, 2),
                'total' => round($rideTotalEarnings, 2),
            ],

            'total_trips' => $totalTrips,
            'total_rides' => $totalRides,
        ];
    }
}
