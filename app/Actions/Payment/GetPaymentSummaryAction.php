<?php

namespace App\Actions\Payment;

use App\Models\Payment;
use App\Models\Investor;
use App\Models\Commission;
use App\Enums\PaymentStatusEnums;
use App\Enums\DeliveryStatusEnums;
use App\Enums\InvestorStatusEnums;
use Illuminate\Support\Facades\DB;
use App\DTOs\Payment\PaymentSummaryDTO;
use App\Enums\InvestorApplicationStatusEnums;

class GetPaymentSummaryAction
{

    public function execute(): PaymentSummaryDTO
    {
        $payments = Payment::where('status', PaymentStatusEnums::PAID->value);

        $totalCollected = (clone $payments)
            ->whereNotNull('delivery_id')
            ->sum(DB::raw('COALESCE(final_price, amount)'));

        $deliveryRevenue = (clone $payments)
            ->whereNotNull('delivery_id')
            ->sum(DB::raw('COALESCE(original_price, amount)'));

        // Count of completed deliveries
        $deliveryTotal = (clone $payments)
            ->whereNotNull('delivery_id')
            ->distinct('delivery_id')
            ->count('delivery_id');

        // Cross-database safe investment sum
        $investmentTotal = Investor::where('status', InvestorStatusEnums::ACTIVE->value)
            ->where('application_status', InvestorApplicationStatusEnums::APPROVED->value)
            ->when(DB::connection()->getDriverName() === 'pgsql', function ($query) {
                return $query->sum(DB::raw('CAST(investment_amount AS NUMERIC)'));
            }, function ($query) {
                return $query->sum('investment_amount');
            });

        $totalOriginal = (clone $payments)
            ->whereNotNull('delivery_id')
            ->sum(DB::raw('COALESCE(original_price, amount)'));

        $totalSubsidy = round($totalOriginal - $totalCollected, 2);

        $commissions = Commission::pluck('percentage', 'role');

        $deliveryBreakdown = [
            'driver'   => round(($commissions['driver'] ?? 0) * $deliveryRevenue / 100, 2),
            'partner'  => round(($commissions['partner'] ?? 0) * $deliveryRevenue / 100, 2),
            'investor' => round(($commissions['investor'] ?? 0) * $deliveryRevenue / 100, 2),
            'platform' => round(($commissions['platform'] ?? 0) * $deliveryRevenue / 100, 2),
        ];

        return new PaymentSummaryDTO(
            totalOriginal: $totalOriginal,
            totalCollected: $totalCollected,
            deliveryTotal: $deliveryTotal,
            investmentTotal: $investmentTotal,
            deliveryRevenue: $deliveryRevenue,
            totalSubsidy: $totalSubsidy,
            deliveryBreakdown: $deliveryBreakdown,
            investmentBreakdown: [] //Add logic later
        );
    }
}
