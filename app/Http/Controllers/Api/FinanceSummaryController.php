<?php

namespace App\Http\Controllers\Api;


use App\Enums\PaymentStatusEnums;
use App\Http\Controllers\Controller;

use App\Models\Payment;
use App\Services\FinancialSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function subscriptionEarnings(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasAnyRole( 'super_admin')) {
            return failureResponse('Unauthorized', 403);
        }

        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');
        $perPage   = min($request->query('per_page', 10), 50);

        $query = Payment::query()
            ->whereNotNull('payments.subscription_id')
            ->where('payments.status', PaymentStatusEnums::PAID)
            ->when($startDate, fn($q) => $q->whereDate('paid_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('paid_at', '<=', $endDate))
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw('
            subscription_plans.id as plan_id,
            subscription_plans.name as plan_name,
            SUM(payments.amount) as total_earnings,
            COUNT(payments.id) as total_transactions
        ')
            ->groupBy('subscription_plans.id', 'subscription_plans.name');

        $earnings = $query->paginate($perPage);

        return successResponse('Subscription earnings retrieved successfully', $earnings);
    }
}
