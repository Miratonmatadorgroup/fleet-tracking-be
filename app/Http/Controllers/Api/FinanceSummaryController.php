<?php

namespace App\Http\Controllers\Api;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Services\FinancialSummaryService;

class FinanceSummaryController extends Controller
{
    public function platformFinancialSummary(Request $request)
    {
        $summary = app(FinancialSummaryService::class)
            ->calculate($request);

        return successResponse(
            'Platform financial summary',
            [
                'gross_income' => $summary['gross_income'],
                'operational_revenue' => $summary['operational_revenue'],

                'funds_breakdown' => [
                    'platform_net_income' => $summary['platform_net_income'],
                    'investor_funds' => $summary['investor_funds'],
                    'bills_payment_revenue' => $summary['bills_payment_revenue'] ?? 0,
                ],

                'spendable_balance' => $summary['spendable_balance'],
            ]
        );
    }
}
