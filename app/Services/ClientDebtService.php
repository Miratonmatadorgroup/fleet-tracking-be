<?php
namespace App\Services;

use App\Models\Payment;
use App\Models\ApiClient;
use App\Enums\PaymentStatusEnums;
use App\Models\FundReconciliation;
use Illuminate\Support\Facades\DB;
use App\Enums\FundsReconcilationStatusEnums;

class ClientDebtService
{
    public function calculateDebtSummary(ApiClient $client)
    {
        $baseQuery = Payment::query()
            ->where('status', PaymentStatusEnums::PAID)
            ->where('api_client_id', $client->id);

        $totalDeliveries = (clone $baseQuery)->count();
        $totalFinal      = (clone $baseQuery)->sum(DB::raw('COALESCE(final_price, amount)'));
        $totalOriginal   = (clone $baseQuery)->sum(DB::raw('COALESCE(original_price, amount)'));

        $totalAmountOwed = $totalOriginal;

        $fundReconciliation = FundReconciliation::where('api_client_id', $client->id)->first();

        if ($fundReconciliation) {
            $fundReconciliation->update([
                'total_amount_owed' => $totalAmountOwed,
                'balance_owed'      => $totalAmountOwed - $fundReconciliation->paid_amount,
            ]);
        } else {
            $fundReconciliation = FundReconciliation::create([
                'api_client_id'     => $client->id,
                'total_amount_owed' => $totalAmountOwed,
                'paid_amount'       => 0,
                'balance_owed'      => $totalAmountOwed,
                'received_amount'   => 0,
                'status'            => FundsReconcilationStatusEnums::NOT_PAID_OFF,
            ]);
        }

        return [
            'partner_name' => $client->name,
            'email'        => $client->customer->email ?? null,
            'data'         => [
                'currency'            => 'NGN',
                'total_deliveries'    => $totalDeliveries,
                'logistics_entitled'  => number_format($totalOriginal, 2, '.', ''),
                'customer_paid'       => number_format($totalFinal, 2, '.', ''),
                'subsidy_covered'     => number_format($totalOriginal - $totalFinal, 2, '.', ''),
                'fund_reconciliation' => $fundReconciliation,
            ]
        ];
    }
}
