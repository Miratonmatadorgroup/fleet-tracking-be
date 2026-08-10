<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Services\FinancialSummaryService;
use App\Services\SubscriptionAnalyticsService;
use Illuminate\Http\Request;

class FinanceSummaryController extends Controller
{
    public function platformFinancialSummary(Request $request)
    {
        $summary = app(FinancialSummaryService::class)
            ->calculate($request);

        return successResponse(
            'Platform financial summary retrieved successfully',
            [
                'gross_income' => $summary['gross_income'],

                'operational_revenue' =>
                $summary['operational_revenue'],

                'platform_net_income' =>
                $summary['platform_net_income'],

                'total_subscription_payments' =>
                $summary['total_subscription_payments'],

                'total_paying_users' =>
                $summary['total_paying_users'],

                'average_revenue_per_user' =>
                $summary['average_revenue_per_user'],
            ]
        );
    }

    public function subscriptionAnalytics(Request $request)
    {
        $analytics = app(SubscriptionAnalyticsService::class)
            ->getAnalytics($request);

        return successResponse(
            'Subscription analytics retrieved successfully',
            $analytics
        );
    }
}
