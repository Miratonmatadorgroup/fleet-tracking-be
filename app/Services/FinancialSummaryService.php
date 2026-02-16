<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\ExternalBankService;

class FinancialSummaryService
{
    public function calculate(Request $request): array
    {
        $internal = app()->call(
            'App\Http\Controllers\Api\DeliveryController@internalDeliveryRevenue',
            ['request' => $request]
        )->getData(true);

        $external = app()->call(
            'App\Http\Controllers\Api\DeliveryController@externalDeliveryRevenue',
            ['request' => $request]
        )->getData(true);

        $ridePool = app()->call(
            'App\Http\Controllers\Api\RideBookingController@ridePoolRevenue',
            ['request' => $request]
        )->getData(true);

        $invested = app()->call(
            'App\Http\Controllers\Api\InvestorController@totalInvestedFunds'
        )->getData(true);

        $billsCommission = $this->getBillsCommissionRevenue($request);

        // Extract values safely
        $internalRevenue = $internal['data']['total_revenue'] ?? 0;
        $externalRevenue = $external['data']['total_revenue'] ?? 0;
        $ridePoolRevenue = $ridePool['data']['total_revenue'] ?? 0;
        $totalInvested   = $invested['data']['total_invested'] ?? 0;

        // Platform commissions
        $platformInternal = $internal['data']['breakdown']['platform_commission'] ?? 0;
        $platformExternal = $external['data']['breakdown']['platform_commission'] ?? 0;
        $platformRidePool = $ridePool['data']['breakdown']['platform_commission'] ?? 0;
        $platformBills = $billsCommission;

        return [
            'gross_income' => round(
                $internalRevenue +
                    $externalRevenue +
                    $ridePoolRevenue +
                    $totalInvested   +
                    $platformBills,
                2
            ),

            'operational_revenue' => round(
                $internalRevenue +
                    $externalRevenue +
                    $ridePoolRevenue +
                    $platformBills,
                2
            ),

            'platform_net_income' => round(
                $platformInternal +
                    $platformExternal +
                    $platformRidePool +
                    $platformBills,
                2
            ),

            'investor_funds' => round($totalInvested, 2),

            'spendable_balance' => round(
                $platformInternal +
                    $platformExternal +
                    $platformRidePool +
                    $platformBills,
                2
            ),
            'bills_payment_revenue' => round($platformBills, 2),
        ];
    }

    /**
     * Fetch & sum bills payment commissions from Shanono
     */
    protected function getBillsCommissionRevenue(Request $request): float
    {
        try {
            $filters = array_filter([
                'start_date' => $request->start_date ?? null,
                'end_date'   => $request->end_date ?? null,
                'per_page'   => 100, // max allowed
            ]);

            $response = app(ExternalBankService::class)
                ->getCommissionTransactions($filters);

            // Prefer total_commissions if present
            if (isset($response['total_commissions'])) {
                return (float) $response['total_commissions'];
            }

            // Fallback: sum commission list
            return collect($response['commissions'] ?? [])
                ->where('status', 'success')
                ->sum('amount');
        } catch (\Throwable $e) {
            Log::error('Bills commission summary failed', [
                'error' => $e->getMessage(),
            ]);

            return 0.0; // Never break finance summary
        }
    }
}
