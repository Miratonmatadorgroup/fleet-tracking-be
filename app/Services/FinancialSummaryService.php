<?php

namespace App\Services;

use App\Enums\PaymentStatusEnums;
use App\Models\Payment;
use Illuminate\Http\Request;

class FinancialSummaryService
{
    public function calculate(Request $request): array
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        /*
        |--------------------------------------------------------------------------
        | SUBSCRIPTION PAYMENTS QUERY
        |--------------------------------------------------------------------------
        */

        $subscriptionPaymentsQuery = Payment::query()
            ->whereNotNull('payments.subscription_id')
            ->where('payments.status', PaymentStatusEnums::PAID)
            ->when(
                $startDate,
                fn ($q) => $q->whereDate(
                    'payments.paid_at',
                    '>=',
                    $startDate
                )
            )
            ->when(
                $endDate,
                fn ($q) => $q->whereDate(
                    'payments.paid_at',
                    '<=',
                    $endDate
                )
            );

        /*
        |--------------------------------------------------------------------------
        | TOTAL SUBSCRIPTION REVENUE
        |--------------------------------------------------------------------------
        */

        $totalSubscriptionRevenue = (clone $subscriptionPaymentsQuery)
            ->sum('payments.amount');

        /*
        |--------------------------------------------------------------------------
        | TOTAL SUBSCRIPTION PAYMENTS
        |--------------------------------------------------------------------------
        */

        $totalSubscriptionPayments = (clone $subscriptionPaymentsQuery)
            ->count('payments.id');

        /*
        |--------------------------------------------------------------------------
        | UNIQUE PAYING USERS
        |--------------------------------------------------------------------------
        */

        $totalPayingUsers = (clone $subscriptionPaymentsQuery)
            ->join(
                'subscriptions',
                'payments.subscription_id',
                '=',
                'subscriptions.id'
            )
            ->whereNotNull('subscriptions.user_id')
            ->distinct()
            ->count('subscriptions.user_id');

        /*
        |--------------------------------------------------------------------------
        | AVERAGE REVENUE PER USER
        |--------------------------------------------------------------------------
        */

        $averageRevenuePerUser = $totalPayingUsers > 0
            ? round(
                (float) $totalSubscriptionRevenue
                / $totalPayingUsers,
                2
            )
            : 0;

        /*
        |--------------------------------------------------------------------------
        | RETURN FINANCIAL SUMMARY
        |--------------------------------------------------------------------------
        */

        return [
            'gross_income' => round(
                (float) $totalSubscriptionRevenue,
                2
            ),

            'operational_revenue' => round(
                (float) $totalSubscriptionRevenue,
                2
            ),

            'platform_net_income' => round(
                (float) $totalSubscriptionRevenue,
                2
            ),

            'total_subscription_payments' =>
                (int) $totalSubscriptionPayments,

            'total_paying_users' =>
                (int) $totalPayingUsers,

            'average_revenue_per_user' =>
                (float) $averageRevenuePerUser,
        ];
    }
}
