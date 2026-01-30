<?php

namespace Database\Seeders;

use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Services\ExternalBankService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BackfillShanonoWalletBalancesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var ExternalBankService $shanono */
        $shanono = app(ExternalBankService::class);

        Wallet::query()
            ->where('external_bank', 'Shanono Bank')
            ->whereNotNull('external_account_number')
            ->chunkById(50, function ($wallets) use ($shanono) {

                foreach ($wallets as $wallet) {
                    try {
                        $balance = $shanono->getAccountBalance(
                            $wallet->external_account_number
                        );

                        $wallet->update([
                            'external_account_id'        => $balance['account_id'] ?? null,
                            'external_available_balance' => $balance['available_balance'],
                            'external_book_balance'      => $balance['book_balance'],
                        ]);

                        Log::info('Wallet external balance backfilled', [
                            'wallet_id' => $wallet->id,
                            'account'   => $wallet->external_account_number,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to backfill wallet balance', [
                            'wallet_id' => $wallet->id,
                            'account'   => $wallet->external_account_number,
                            'error'     => $e->getMessage(),
                        ]);

                        // DO NOT throw — continue processing other wallets
                        continue;
                    }
                }
            });
    }
}
