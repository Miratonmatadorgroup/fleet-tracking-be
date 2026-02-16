<?php

namespace App\Actions\Payment;

use Carbon\Carbon;
use App\Models\Payment;
use App\Models\Investor;
use App\Models\RidePool;
use Illuminate\Http\Request;
use App\Enums\PaymentStatusEnums;
use App\Enums\InvestorStatusEnums;

use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Enums\InvestorApplicationStatusEnums;

class GetEarningsSummaryAction
{
    // public function execute(?string $range = null): array
    // {
    //     $tz = config('app.timezone', 'UTC');
    //     $now = Carbon::now($tz);
    //     $isPgsql = DB::connection()->getDriverName() === 'pgsql';

    //     $deliveryPayments = Payment::where('status', PaymentStatusEnums::PAID->value)
    //         ->whereNotNull('delivery_id');

    //     $investmentBaseQuery = Investor::where('status', InvestorStatusEnums::ACTIVE->value)
    //         ->where('application_status', InvestorApplicationStatusEnums::APPROVED->value);

    //     //TIME ANCHORS
    //     $today = $now->copy()->startOfDay();
    //     $startOfWeek = $now->copy()->startOfWeek();
    //     $startOfMonth = $now->copy()->startOfMonth();
    //     $startOfYear = $now->copy()->startOfYear();

    //     // DELIVERY EARNINGS
    //     $deliveryDaily = (clone $deliveryPayments)
    //         ->whereDate('created_at', $today)
    //         ->sum(DB::raw('COALESCE(final_price, amount)'));

    //     $deliveryWeekly = (clone $deliveryPayments)
    //         ->whereBetween('created_at', [$startOfWeek, $now])
    //         ->sum(DB::raw('COALESCE(final_price, amount)'));

    //     $deliveryMonthly = (clone $deliveryPayments)
    //         ->whereBetween('created_at', [$startOfMonth, $now])
    //         ->sum(DB::raw('COALESCE(final_price, amount)'));

    //     $deliveryYearly = (clone $deliveryPayments)
    //         ->whereBetween('created_at', [$startOfYear, $now])
    //         ->sum(DB::raw('COALESCE(final_price, amount)'));


    //     //INVESTMENT EARNINGS (timestamp-aware)
    //     $investmentDaily = (clone $investmentBaseQuery)
    //         ->whereDate('created_at', $today)
    //         ->when(
    //             $isPgsql,
    //             fn($q) => $q->sum(DB::raw('CAST(investment_amount AS NUMERIC)')),
    //             fn($q) => $q->sum('investment_amount')
    //         );

    //     $investmentWeekly = (clone $investmentBaseQuery)
    //         ->whereBetween('created_at', [$startOfWeek, $now])
    //         ->when(
    //             $isPgsql,
    //             fn($q) => $q->sum(DB::raw('CAST(investment_amount AS NUMERIC)')),
    //             fn($q) => $q->sum('investment_amount')
    //         );

    //     $investmentMonthly = (clone $investmentBaseQuery)
    //         ->whereBetween('created_at', [$startOfMonth, $now])
    //         ->when(
    //             $isPgsql,
    //             fn($q) => $q->sum(DB::raw('CAST(investment_amount AS NUMERIC)')),
    //             fn($q) => $q->sum('investment_amount')
    //         );

    //     $investmentYearly = (clone $investmentBaseQuery)
    //         ->whereBetween('created_at', [$startOfYear, $now])
    //         ->when(
    //             $isPgsql,
    //             fn($q) => $q->sum(DB::raw('CAST(investment_amount AS NUMERIC)')),
    //             fn($q) => $q->sum('investment_amount')
    //         );

    //     //BUILD CHARTs
    //     $deliveryCharts = [
    //         'daily'   => $this->buildChart($deliveryPayments, $isPgsql, 'daily', $now->copy()->subDays(7), $tz),
    //         'weekly'  => $this->buildChart($deliveryPayments, $isPgsql, 'weekly', $now->copy()->subWeeks(12), $tz),
    //         'monthly' => $this->buildChart($deliveryPayments, $isPgsql, 'monthly', $now->copy()->subMonths(12), $tz),
    //         'yearly'  => $this->buildChart($deliveryPayments, $isPgsql, 'yearly', $now->copy()->subYears(5), $tz),
    //     ];

    //     $investmentCharts = [
    //         'daily'   => $this->buildInvestmentChart($investmentBaseQuery, $isPgsql, 'daily', $now->copy()->subDays(7), $tz),
    //         'weekly'  => $this->buildInvestmentChart($investmentBaseQuery, $isPgsql, 'weekly', $now->copy()->subWeeks(12), $tz),
    //         'monthly' => $this->buildInvestmentChart($investmentBaseQuery, $isPgsql, 'monthly', $now->copy()->subMonths(12), $tz),
    //         'yearly'  => $this->buildInvestmentChart($investmentBaseQuery, $isPgsql, 'yearly', $now->copy()->subYears(5), $tz),
    //     ];

    //     //COMBINED
    //     $combined = [
    //         'daily'   => $deliveryDaily + $investmentDaily,
    //         'weekly'  => $deliveryWeekly + $investmentWeekly,
    //         'monthly' => $deliveryMonthly + $investmentMonthly,
    //         'yearly'  => $deliveryYearly + $investmentYearly,
    //         'charts'  => [
    //             'daily'   => $this->mergeCharts($deliveryCharts['daily'], $investmentCharts['daily']),
    //             'weekly'  => $this->mergeCharts($deliveryCharts['weekly'], $investmentCharts['weekly']),
    //             'monthly' => $this->mergeCharts($deliveryCharts['monthly'], $investmentCharts['monthly']),
    //             'yearly'  => $this->mergeCharts($deliveryCharts['yearly'], $investmentCharts['yearly']),
    //         ],
    //     ];

    //     //RETURN SPECIFIC RANGE
    //     if ($range) {
    //         $range = strtolower($range);
    //         if (!in_array($range, ['daily', 'weekly', 'monthly', 'yearly'])) {
    //             abort(400, "Invalid range. Allowed values: daily, weekly, monthly, yearly.");
    //         }

    //         return [
    //             'range' => $range,
    //             'delivery' => round(${"delivery" . ucfirst($range)}, 2),
    //             'investment' => round(${"investment" . ucfirst($range)}, 2),
    //             'combined' => round($combined[$range], 2),
    //             'chart' => $combined['charts'][$range],
    //         ];
    //     }

    //     return [
    //         'delivery' => [
    //             'daily' => round($deliveryDaily, 2),
    //             'weekly' => round($deliveryWeekly, 2),
    //             'monthly' => round($deliveryMonthly, 2),
    //             'yearly' => round($deliveryYearly, 2),
    //             'charts' => $deliveryCharts,
    //         ],
    //         'investment' => [
    //             'daily' => round($investmentDaily, 2),
    //             'weekly' => round($investmentWeekly, 2),
    //             'monthly' => round($investmentMonthly, 2),
    //             'yearly' => round($investmentYearly, precision: 2),
    //             'charts' => $investmentCharts,
    //         ],
    //         'combined' => $combined,
    //     ];
    // }

    public function execute(?string $range = null): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';



        //TIME ANCHORS
        $today = $now->copy()->startOfDay();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();


        $deliveryDaily = $this->getPlatformDeliveryRevenue(
            new Request(['from' => $today->toDateString(), 'to' => $today->toDateString()])
        );

        $deliveryWeekly = $this->getPlatformDeliveryRevenue(
            new Request(['from' => $startOfWeek->toDateString(), 'to' => $now->toDateString()])
        );

        $deliveryMonthly = $this->getPlatformDeliveryRevenue(
            new Request(['from' => $startOfMonth->toDateString(), 'to' => $now->toDateString()])
        );

        $deliveryYearly = $this->getPlatformDeliveryRevenue(
            new Request(['from' => $startOfYear->toDateString(), 'to' => $now->toDateString()])
        );


        $deliveryPayments = Payment::where(
            'status',
            PaymentStatusEnums::PAID->value
        )->whereNotNull('delivery_id');

        $investmentBaseQuery = Investor::where('status', InvestorStatusEnums::ACTIVE->value)
            ->where('application_status', InvestorApplicationStatusEnums::APPROVED->value);

        //INVESTMENT EARNINGS (timestamp-aware)
        $investmentDaily = (clone $investmentBaseQuery)
            ->whereDate('created_at', $today)
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(investment_amount AS NUMERIC)')
                    : 'investment_amount'
            );

        $investmentWeekly = (clone $investmentBaseQuery)
            ->whereBetween('created_at', [$startOfWeek, $now])
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(investment_amount AS NUMERIC)')
                    : 'investment_amount'
            );

        $investmentMonthly = (clone $investmentBaseQuery)
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(investment_amount AS NUMERIC)')
                    : 'investment_amount'
            );

        $investmentYearly = (clone $investmentBaseQuery)
            ->whereBetween('created_at', [$startOfYear, $now])
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(investment_amount AS NUMERIC)')
                    : 'investment_amount'
            );

        $ridePoolBaseQuery = RidePool::where(
            'payment_status',
            RidePoolPaymentStatusEnums::PAID->value
        )
            ->where(
                'status',
                RidePoolStatusEnums::RIDE_ENDED->value
            );

        $ridePoolDaily = (clone $ridePoolBaseQuery)
            ->whereDate('created_at', $today)
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(estimated_cost AS NUMERIC)')
                    : 'estimated_cost'
            );

        $ridePoolWeekly = (clone $ridePoolBaseQuery)
            ->whereBetween('created_at', [$startOfWeek, $now])
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(estimated_cost AS NUMERIC)')
                    : 'estimated_cost'
            );

        $ridePoolMonthly = (clone $ridePoolBaseQuery)
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(estimated_cost AS NUMERIC)')
                    : 'estimated_cost'
            );

        $ridePoolYearly = (clone $ridePoolBaseQuery)
            ->whereBetween('created_at', [$startOfYear, $now])
            ->sum(
                $isPgsql
                    ? DB::raw('CAST(estimated_cost AS NUMERIC)')
                    : 'estimated_cost'
            );




        $deliveryCharts = [
            'daily' => $this->buildChart(
                $deliveryPayments,
                $isPgsql,
                'daily',
                $now->copy()->subDays(7),
                $tz
            ),
            'weekly' => $this->buildChart(
                $deliveryPayments,
                $isPgsql,
                'weekly',
                $now->copy()->subWeeks(12),
                $tz
            ),
            'monthly' => $this->buildChart(
                $deliveryPayments,
                $isPgsql,
                'monthly',
                $now->copy()->subMonths(12),
                $tz
            ),
            'yearly' => $this->buildChart(
                $deliveryPayments,
                $isPgsql,
                'yearly',
                $now->copy()->subYears(5),
                $tz
            ),
        ];

        $investmentCharts = [
            'daily' => $this->buildInvestmentChart(
                $investmentBaseQuery,
                $isPgsql,
                'daily',
                $now->copy()->subDays(7),
                $tz
            ),
            'weekly' => $this->buildInvestmentChart(
                $investmentBaseQuery,
                $isPgsql,
                'weekly',
                $now->copy()->subWeeks(12),
                $tz
            ),
            'monthly' => $this->buildInvestmentChart(
                $investmentBaseQuery,
                $isPgsql,
                'monthly',
                $now->copy()->subMonths(12),
                $tz
            ),
            'yearly' => $this->buildInvestmentChart(
                $investmentBaseQuery,
                $isPgsql,
                'yearly',
                $now->copy()->subYears(5),
                $tz
            ),
        ];

        $ridePoolCharts = [
            'daily' => $this->buildRidePoolChart(
                $ridePoolBaseQuery,
                $isPgsql,
                'daily',
                $now->copy()->subDays(7),
                $tz
            ),
            'weekly' => $this->buildRidePoolChart(
                $ridePoolBaseQuery,
                $isPgsql,
                'weekly',
                $now->copy()->subWeeks(12),
                $tz
            ),
            'monthly' => $this->buildRidePoolChart(
                $ridePoolBaseQuery,
                $isPgsql,
                'monthly',
                $now->copy()->subMonths(12),
                $tz
            ),
            'yearly' => $this->buildRidePoolChart(
                $ridePoolBaseQuery,
                $isPgsql,
                'yearly',
                $now->copy()->subYears(5),
                $tz
            ),
        ];


        //COMBINED
        $combined = [
            'daily'   => $deliveryDaily + $ridePoolDaily + $investmentDaily,
            'weekly'  => $deliveryWeekly + $ridePoolWeekly + $investmentWeekly,
            'monthly' => $deliveryMonthly + $ridePoolMonthly + $investmentMonthly,
            'yearly'  => $deliveryYearly + $ridePoolYearly + $investmentYearly,
            'charts'  => [
                'daily' => $this->mergeCharts(
                    $this->mergeCharts($deliveryCharts['daily'], $ridePoolCharts['daily']),
                    $investmentCharts['daily']
                ),
                'weekly' => $this->mergeCharts(
                    $this->mergeCharts($deliveryCharts['weekly'], $ridePoolCharts['weekly']),
                    $investmentCharts['weekly']
                ),
                'monthly' => $this->mergeCharts(
                    $this->mergeCharts($deliveryCharts['monthly'], $ridePoolCharts['monthly']),
                    $investmentCharts['monthly']
                ),
                'yearly' => $this->mergeCharts(
                    $this->mergeCharts($deliveryCharts['yearly'], $ridePoolCharts['yearly']),
                    $investmentCharts['yearly']
                ),
            ],
        ];


        //RETURN SPECIFIC RANGE
        if ($range) {
            $range = strtolower($range);

            if (!in_array($range, ['daily', 'weekly', 'monthly', 'yearly'])) {
                abort(
                    400,
                    'Invalid range. Allowed values: daily, weekly, monthly, yearly.'
                );
            }

            return [
                'range'      => $range,
                'delivery'   => round(${"delivery" . ucfirst($range)}, 2),
                'ride_pool'  => round(${"ridePool" . ucfirst($range)}, 2),
                'investment' => round(${"investment" . ucfirst($range)}, 2),
                'combined'   => round($combined[$range], 2),
                'chart'      => $combined['charts'][$range],
            ];
        }

        // =====================
        // FULL RESPONSE
        // =====================
        return [
            'delivery' => [
                'daily'   => round($deliveryDaily, 2),
                'weekly'  => round($deliveryWeekly, 2),
                'monthly' => round($deliveryMonthly, 2),
                'yearly'  => round($deliveryYearly, 2),
                'charts'  => $deliveryCharts,
            ],
            'ride_pool' => [
                'daily'   => round($ridePoolDaily, 2),
                'weekly'  => round($ridePoolWeekly, 2),
                'monthly' => round($ridePoolMonthly, 2),
                'yearly'  => round($ridePoolYearly, 2),
                'charts'  => $ridePoolCharts,
            ],
            'investment' => [
                'daily'   => round($investmentDaily, 2),
                'weekly'  => round($investmentWeekly, 2),
                'monthly' => round($investmentMonthly, 2),
                'yearly'  => round($investmentYearly, 2),
                'charts'  => $investmentCharts,
            ],
            'combined' => $combined,
        ];
    }

    private function getPlatformDeliveryRevenue(Request $request): float
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

        return ($internal['data']['breakdown']['platform_commission'] ?? 0) +
            ($external['data']['breakdown']['platform_commission'] ?? 0) +
            ($ridePool['data']['breakdown']['platform_commission'] ?? 0);
    }

    // =====================
    //  DELIVERY CHART BUILDER
    // =====================
    private function buildChart($query, bool $isPgsql, string $range, Carbon $fromDate, string $tz)
    {
        $dateField = $this->getDateField($isPgsql, $range);

        $results = (clone $query)
            ->where('created_at', '>=', $fromDate)
            ->select(
                DB::raw("$dateField as period"),
                DB::raw('SUM(COALESCE(final_price, amount)) as total')
            )
            ->groupBy(DB::raw($dateField))
            ->orderBy('period', 'asc')
            ->get();

        return $this->normalizePeriods($results, $isPgsql, $range, $tz);
    }

    // =====================
    //  RIDE POOL CHART BUILDER
    // =====================
    private function buildRidePoolChart($query, bool $isPgsql, string $range, Carbon $fromDate, string $tz)
    {
        $dateField = $this->getDateField($isPgsql, $range);

        $results = (clone $query)
            ->where('created_at', '>=', $fromDate)
            ->select(
                DB::raw("$dateField as period"),
                DB::raw('SUM(COALESCE(estimated_cost AS NUMERIC)) as total')
            )
            ->groupBy(DB::raw($dateField))
            ->orderBy('period', 'asc')
            ->get();

        return $this->normalizePeriods($results, $isPgsql, $range, $tz);
    }

    // =====================
    //  INVESTMENT CHART BUILDER
    // =====================
    private function buildInvestmentChart($query, bool $isPgsql, string $range, Carbon $fromDate, string $tz)
    {
        $dateField = $this->getDateField($isPgsql, $range);

        $results = (clone $query)
            ->where('created_at', '>=', $fromDate)
            ->select(
                DB::raw("$dateField as period"),
                $isPgsql
                    ? DB::raw('SUM(CAST(investment_amount AS NUMERIC)) as total')
                    : DB::raw('SUM(investment_amount) as total')
            )
            ->groupBy(DB::raw($dateField))
            ->orderBy('period', 'asc')
            ->get();

        return $this->normalizePeriods($results, $isPgsql, $range, $tz);
    }

    // =====================
    //  HELPERS
    // =====================
    private function getDateField(bool $isPgsql, string $range): string
    {
        return match ($range) {
            'daily' => $isPgsql
                ? "CAST(created_at AT TIME ZONE 'UTC' AS DATE)"
                : "DATE(CONVERT_TZ(created_at, '+00:00', @@session.time_zone))",
            'weekly' => $isPgsql
                ? "DATE_TRUNC('week', created_at AT TIME ZONE 'UTC')"
                : "YEARWEEK(CONVERT_TZ(created_at, '+00:00', @@session.time_zone), 1)",
            'monthly' => $isPgsql
                ? "TO_CHAR(created_at AT TIME ZONE 'UTC', 'YYYY-MM')"
                : "DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', @@session.time_zone), '%Y-%m')",
            'yearly' => $isPgsql
                ? "TO_CHAR(created_at AT TIME ZONE 'UTC', 'YYYY')"
                : "YEAR(CONVERT_TZ(created_at, '+00:00', @@session.time_zone))",
            default => $isPgsql ? 'CAST(created_at AS DATE)' : 'DATE(created_at)',
        };
    }

    private function normalizePeriods($results, bool $isPgsql, string $range, string $tz)
    {
        if ($range === 'weekly') {
            $results = $results->map(function ($row) use ($isPgsql, $tz) {
                if ($isPgsql) {
                    $row->period = Carbon::parse($row->period, 'UTC')->setTimezone($tz)->startOfWeek()->format('Y-m-d');
                } else {
                    $year = substr($row->period, 0, 4);
                    $week = substr($row->period, 4, 2);
                    $row->period = Carbon::now($tz)->setISODate($year, $week)->startOfWeek()->format('Y-m-d');
                }
                return $row;
            });
        }
        return $results;
    }

    private function mergeCharts($chartA, $chartB)
    {
        $merged = collect();
        $allPeriods = collect($chartA)->pluck('period')
            ->merge(collect($chartB)->pluck('period'))
            ->unique()
            ->sort();

        foreach ($allPeriods as $period) {
            $totalA = collect($chartA)->firstWhere('period', $period)->total ?? 0;
            $totalB = collect($chartB)->firstWhere('period', $period)->total ?? 0;

            $merged->push((object)[
                'period' => $period,
                'total' => $totalA + $totalB,
            ]);
        }

        return $merged->values();
    }
}
