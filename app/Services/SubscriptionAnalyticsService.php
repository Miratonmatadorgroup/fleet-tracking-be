<?php

namespace App\Services;

use App\Enums\PaymentStatusEnums;
use App\Enums\SubscriptionStatusEnums;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionAnalyticsService
{
    public function getAnalytics(Request $request): array
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');


        /* TOTAL SUBSCRIPTION EARNINGS, Actual money received from successful subscription payments. */
        $totalEarnings = Payment::query()
            ->whereNotNull('subscription_id')
            ->where('status', PaymentStatusEnums::PAID)
            ->when(
                $startDate,
                fn ($q) => $q->whereDate('paid_at', '>=', $startDate)
            )
            ->when(
                $endDate,
                fn ($q) => $q->whereDate('paid_at', '<=', $endDate)
            )
            ->sum('amount');


        /* ACTIVE SUBSCRIPTIONS, An active subscription means: - status = ACTIVE, - end_date is today or later */
        $activeSubscriptionsQuery = Subscription::query()
            ->where('status', SubscriptionStatusEnums::ACTIVE)
            ->whereDate('end_date', '>=', today());

        $totalActiveSubscriptions = (clone $activeSubscriptionsQuery)
            ->count();


        /*
        |--------------------------------------------------------------------------
        | USERS WITH ACTIVE SUBSCRIPTIONS
        |--------------------------------------------------------------------------
        |
        | A user can have multiple active subscriptions.
        |
        | Example:
        |
        | John
        | ├── Van subscription
        | ├── Truck subscription
        | └── Car subscription
        |
        | Active subscriptions = 3
        | Active subscribers = 1
        |
        */

        $usersWithActiveSubscriptions = (clone $activeSubscriptionsQuery)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');


        /*
        |--------------------------------------------------------------------------
        | AVERAGE REVENUE PER USER
        |--------------------------------------------------------------------------
        |
        | Total subscription revenue divided by the number of
        | unique users who made subscription payments in the
        | selected period.
        |
        */

        $revenueUsersQuery = Payment::query()
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
            )
            ->join(
                'subscriptions',
                'payments.subscription_id',
                '=',
                'subscriptions.id'
            )
            ->whereNotNull('subscriptions.user_id');

        $uniqueRevenueUsers = (clone $revenueUsersQuery)
            ->distinct('subscriptions.user_id')
            ->count('subscriptions.user_id');

        $averageRevenuePerUser = $uniqueRevenueUsers > 0
            ? round(
                (float) $totalEarnings / $uniqueRevenueUsers,
                2
            )
            : 0;


        /*
        |--------------------------------------------------------------------------
        | EARNINGS BY PLAN
        |--------------------------------------------------------------------------
        |
        | Revenue breakdown across subscription tiers.
        |
        */

        $earningsByPlan = Payment::query()
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
            )
            ->join(
                'subscriptions',
                'payments.subscription_id',
                '=',
                'subscriptions.id'
            )
            ->join(
                'subscription_plans',
                'subscriptions.plan_id',
                '=',
                'subscription_plans.id'
            )
            ->selectRaw('
                subscription_plans.id AS plan_id,
                subscription_plans.name AS plan_name,
                SUM(payments.amount) AS total_earnings,
                COUNT(payments.id) AS total_transactions
            ')
            ->groupBy(
                'subscription_plans.id',
                'subscription_plans.name'
            )
            ->orderByDesc('total_earnings')
            ->get()
            ->map(function ($plan) {
                return [
                    'plan_id' => $plan->plan_id,
                    'plan_name' => $plan->plan_name,
                    'total_earnings' => (float) $plan->total_earnings,
                    'total_transactions' => (int) $plan->total_transactions,
                ];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | RECENT SUBSCRIPTIONS
        |--------------------------------------------------------------------------
        |
        | Latest successful subscription payments.
        |
        */

        $recentSubscriptions = Payment::query()
            ->whereNotNull('payments.subscription_id')
            ->where('payments.status', PaymentStatusEnums::PAID)
            ->with([
                'subscription.user',
                'subscription.plan',
            ])
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
            )
            ->latest('payments.paid_at')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'payment_id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at?->toIso8601String(),

                    'user' => [
                        'id' => $payment->subscription?->user?->id,
                        'name' => $payment->subscription?->user?->name,
                        'email' => $payment->subscription?->user?->email,
                    ],

                    'plan' => [
                        'id' => $payment->subscription?->plan?->id,
                        'name' => $payment->subscription?->plan?->name,
                    ],
                ];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | RETURN ANALYTICS
        |--------------------------------------------------------------------------
        */

        return [
            'total_earnings' => (float) $totalEarnings,

            'active_subscriptions' => (int) $totalActiveSubscriptions,

            'users_with_active_subscriptions' => (int) $usersWithActiveSubscriptions,

            'average_revenue_per_user' => (float) $averageRevenuePerUser,

            'earnings_by_plan' => $earningsByPlan,

            'recent_subscriptions' => $recentSubscriptions,
        ];
    }
}
