<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use App\Mail\FundReceivedMail;
use App\Enums\PaymentStatusEnums;
use App\Models\FundReconciliation;
use Illuminate\Support\Facades\DB;
use App\Mail\FundPaymentLoggedMail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Enums\FundsReconcilationStatusEnums;


class FundReconciliationController extends Controller
{
    // FOR GENERAL DEBT CALCULATION OF ALL EXTERNAL USERS STARTS HERE
    public function externalClientsDebtSummary()
    {
        try {
            $clients = ApiClient::where('active', true)->get();

            $results = [];

            foreach ($clients as $client) {
                $baseQuery = Payment::query()
                    ->where('status', PaymentStatusEnums::PAID)
                    ->where('api_client_id', $client->id);

                // Get totals
                $totalDeliveries = (clone $baseQuery)->count();
                $totalFinal      = (clone $baseQuery)->sum(
                    DB::raw('COALESCE(final_price, amount)')
                );
                $totalSubsidy    = (clone $baseQuery)->sum('subsidy_amount');

                $totalOriginal   = (clone $baseQuery)->sum(
                    DB::raw('COALESCE(original_price, amount)')
                );

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

                $results[] = [
                    'partner_name' => $client->name,
                    'data' => [
                        'currency'            => 'NGN',
                        'total_deliveries'    => $totalDeliveries,
                        'logistics_entitled'  => number_format($totalOriginal, 2, '.', ''),
                        'customer_paid'       => number_format($totalFinal, 2, '.', ''),
                        'subsidy_covered'     => number_format($totalOriginal - $totalFinal, 2, '.', ''),
                        'fund_reconciliation' => $fundReconciliation,
                    ]
                ];
            }

            return successResponse('Funds reconciliation for external users fetched.', $results);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage());
        }
    }
    // FOR GENERAL DEBT CALCULATION OF ALL EXTERNAL USERS ENDS HERE

    // FOR DEBT CALCULATION OF A SPECIFIC EXTERNAL USER STARTS HERE
    public function externalClientDebtSummary($clientId)
    {
        try {
            $client = ApiClient::where('active', true)->findOrFail($clientId);

            $baseQuery = Payment::query()
                ->where('status', PaymentStatusEnums::PAID)
                ->where('api_client_id', $client->id);

            // Get totals
            $totalDeliveries = (clone $baseQuery)->count();
            $totalFinal      = (clone $baseQuery)->sum(DB::raw('COALESCE(final_price, amount)'));
            $totalSubsidy    = (clone $baseQuery)->sum('subsidy_amount');
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

            $data = [
                'partner_name' => $client->name,
                'data' => [
                    'currency'            => 'NGN',
                    'total_deliveries'    => $totalDeliveries,
                    'logistics_entitled'  => number_format($totalOriginal, 2, '.', ''),
                    'customer_paid'       => number_format($totalFinal, 2, '.', ''),
                    'subsidy_covered'     => number_format($totalOriginal - $totalFinal, 2, '.', ''),
                    'fund_reconciliation' => $fundReconciliation,
                ]
            ];

            return successResponse("Funds reconciliation for {$client->name} calculated successfully.", $data);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage());
        }
    }
    // FOR DEBT CALCULATION OF A SPECIFIC EXTERNAL USER ENDS HERE

    public function customizedViewDebt(Request $request)
    {
        $clientId = $request->header('X-External-User-Id');

        if (!$clientId) {
            return failureResponse('Missing X-External-User-Id header', 400);
        }

        try {
            $funds = FundReconciliation::with('apiClient')
                ->where('api_client_id', $clientId)
                ->orderByDesc('created_at')
                ->paginate(20);

            // Transform the collection to add "client_name" first
            $funds->getCollection()->transform(function ($item) {
                return collect([
                    'client_name' => $item->apiClient->name ?? null,
                ])->merge($item->toArray());
            });

            return successResponse('Fund reconciliations fetched', $funds);
        } catch (\Throwable $th) {
            return failureResponse('Unable to fetch fund reconciliations', 500, 'Exception', $th);
        }
    }

    public function pay(Request $request, $id)
    {
        $request->validate([
            'paid_amount' => 'required|numeric|min:1',
        ]);

        $fund = FundReconciliation::with('apiClient')->findOrFail($id);

        //If balance_owed is null or 0, recalc it from total_amount_owed - paid_amount
        if ($fund->balance_owed === null || $fund->balance_owed == 0) {
            $fund->balance_owed = (float) $fund->total_amount_owed - (float) $fund->paid_amount;
        }

        //Strictly block overpayment
        if ($request->paid_amount > $fund->balance_owed) {
            return failureResponse("Payment amount ({$request->paid_amount}) exceeds balance owed ({$fund->balance_owed}).");
        }

        $fund->paid_amount = (float) $fund->paid_amount + (float) $request->paid_amount;

        //Recalculate balance_owed
        $fund->balance_owed = (float) $fund->total_amount_owed - (float) $fund->paid_amount;
        $fund->save();

        //Build payload for notification
        $payload = [
            'fund_id'           => $fund->id,
            'api_client'        => optional($fund->apiClient)->name,
            'paid_amount'      => (float) $request->paid_amount,
            'total_paid_amount' => (float) $fund->paid_amount,
            'total_amount_owed' => (float) $fund->total_amount_owed,
            'balance_owed'      => (float) $fund->balance_owed,
        ];

        foreach (config('services.logistics.logistics_admins', []) as $email) {
            Mail::to($email)->send(new FundPaymentLoggedMail($payload));
        }

        return successResponse('Payment recorded successfully', $payload);
    }


    public function viewAllFunds(Request $request)
    {
        $search = $request->query('search');

        $query = FundReconciliation::with('apiClient')
            ->orderBy('created_at', 'desc');

        if (!empty($search)) {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator, $driver) {
                $q->whereHas('apiClient', function ($clientQuery) use ($search, $likeOperator) {
                    $clientQuery->where('name', $likeOperator, "%{$search}%");
                                });

                if ($driver === 'pgsql') {
                    $q->orWhereRaw("CAST(total_amount_owed AS TEXT) {$likeOperator} ?", ["%{$search}%"])
                        ->orWhereRaw("CAST(paid_amount AS TEXT) {$likeOperator} ?", ["%{$search}%"])
                        ->orWhereRaw("CAST(balance_owed AS TEXT) {$likeOperator} ?", ["%{$search}%"])
                        ->orWhereRaw("CAST(received_amount AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                } else {
                    $q->orWhere('total_amount_owed', $likeOperator, "%{$search}%")
                        ->orWhere('paid_amount', $likeOperator, "%{$search}%")
                        ->orWhere('balance_owed', $likeOperator, "%{$search}%")
                        ->orWhere('received_amount', $likeOperator, "%{$search}%");
                }

                $q->orWhere('status', $likeOperator, "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $reconciliations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Fund reconciliations fetched successfully.',
            'data'    => $reconciliations
        ]);
    }


    public function receive(Request $request, $id)
    {
        $request->validate([
            'received_amount' => 'required|numeric|min:1'
        ]);

        $fund = FundReconciliation::with('apiClient')->findOrFail($id);

        if ((float)$fund->received_amount === (float)$fund->paid_amount) {
            return failureResponse("This fund reconciliation has already been received.", 422);
        }

        if ((float)$request->received_amount !== (float)$fund->paid_amount) {
            return failureResponse("Received amount must equal paid amount", 422);
        }



        $fund->received_amount = (float)$fund->paid_amount;
        $fund->balance_owed    = (float)$fund->total_amount_owed - (float)$fund->received_amount;

        if ($fund->balance_owed > 0) {
            $fund->status = FundsReconcilationStatusEnums::PARTLY_PAID_OFF->value;
        } else {
            $fund->status = FundsReconcilationStatusEnums::TOTALY_PAID_OFF->value;
        }

        $fund->save();

        $payload = [
            'fund_id'               => $fund->id,
            'api_client'            => optional($fund->apiClient)->name,
            'received_amount_delta' => (float)$request->received_amount,
            'received_amount'       => (float)$fund->received_amount,
            'paid_amount'           => (float)$fund->paid_amount,
            'total_amount_owed'     => (float)$fund->total_amount_owed,
            'balance_owed'          => (float)$fund->balance_owed,
            'status'                => $fund->status,
        ];

        foreach (config('services.logistics.logistics_admins', []) as $email) {
            try {
                Mail::to($email)->queue(new FundReceivedMail($payload));
            } catch (\Throwable $e) {
                Log::error("Failed to queue FundReceivedMail to {$email}: " . $e->getMessage());
            }
        }

        return successResponse('Fund reconciliation updated', $payload);
    }
}
