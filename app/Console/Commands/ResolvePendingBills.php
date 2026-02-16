<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ExternalBankService;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;

class ResolvePendingBills extends Command
{
    protected $signature = 'bills:resolve-pending';

    public function handle(ExternalBankService $bank)
    {
        WalletTransaction::where('status', WalletTransactionStatusEnums::PENDING)
            ->whereIn('method', [
                WalletTransactionMethodEnums::DATA,
                WalletTransactionMethodEnums::AIRTIME,
                WalletTransactionMethodEnums::ELECTRICITY,
                WalletTransactionMethodEnums::CABLETV,
            ])
            ->chunkById(50, function ($txns) use ($bank) {

                foreach ($txns as $txn) {
                    try {
                        $status = $bank->checkBillsPayStatus($txn->reference);

                        if ($status === 'success') {
                            DB::transaction(function () use ($txn) {

                                $txn->refresh();

                                if ($txn->status !== WalletTransactionStatusEnums::PENDING) {
                                    return;
                                }

                                $wallet = $txn->wallet()->lockForUpdate()->first();

                                if ($wallet->pending_balance < $txn->amount) {
                                    Log::critical('Pending balance mismatch', [
                                        'txn_id' => $txn->id,
                                        'pending' => $wallet->pending_balance,
                                        'amount' => $txn->amount,
                                    ]);
                                    return;
                                }

                                $wallet->decrement('pending_balance', $txn->amount);

                                $txn->update([
                                    'status' => WalletTransactionStatusEnums::SUCCESS
                                ]);
                            });
                        }

                        if ($status === 'failed') {
                            DB::transaction(function () use ($txn) {

                                $txn->refresh();

                                if ($txn->status !== WalletTransactionStatusEnums::PENDING) {
                                    return;
                                }

                                $wallet = $txn->wallet()->lockForUpdate()->first();

                                if ($wallet->pending_balance < $txn->amount) {
                                    Log::critical('Pending balance mismatch', [
                                        'txn_id' => $txn->id,
                                        'pending' => $wallet->pending_balance,
                                        'amount' => $txn->amount,
                                    ]);
                                    return;
                                }

                                $wallet->increment('available_balance', $txn->amount);
                                $wallet->decrement('pending_balance', $txn->amount);

                                $txn->update([
                                    'status' => WalletTransactionStatusEnums::FAILED
                                ]);
                            });
                        }

                        // pending → do nothing

                    } catch (\Throwable $e) {
                        Log::error('Bills requery failed', [
                            'txn_id'    => $txn->id,
                            'reference' => $txn->reference,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}

