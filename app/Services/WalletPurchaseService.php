<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Events\Wallet\WalletPurchaseCompleted;

class WalletPurchaseService
{
    public function process(
        User $user,
        Wallet $wallet,
        float $amount,
        WalletTransactionMethodEnums|string $method,
        callable $providerCallback,
        array $meta = []
    ): WalletTransaction {

        $walletTransaction = null;

        DB::transaction(function () use (
            $user,
            $wallet,
            $amount,
            $method,
            $providerCallback,
            $meta,
            &$walletTransaction
        ) {
            $reference = strtoupper(
                $method instanceof WalletTransactionMethodEnums
                    ? $method->value
                    : $method
            ) . '-' . Str::random(12);

            $providerResponse = $providerCallback($reference);
            $meta['_provider_response'] = $providerResponse;

            $responseCode = data_get($providerResponse, '0.responseCode');

            if (! in_array($responseCode, [200, 202], true)) {
                throw new \Exception('Provider transaction failed');
            }

            $wallet->decrement('available_balance', $amount);
            $wallet->decrement('total_balance', $amount);


            if ($responseCode === 202) {
                $wallet->increment('pending_balance', $amount);
            }

            $walletTransaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id'   => $user->id,
                'type'      => WalletTransactionTypeEnums::DEBIT,
                'method'    => $method instanceof WalletTransactionMethodEnums
                    ? $method->value
                    : $method,
                'status'    => $responseCode === 200
                    ? WalletTransactionStatusEnums::SUCCESS
                    : WalletTransactionStatusEnums::PENDING,
                'amount'    => $amount,
                'reference' => $reference,
                'meta'      => $meta,
            ]);
        });

        if (! $walletTransaction instanceof WalletTransaction) {
            throw new \RuntimeException('Wallet transaction was not created');
        }

        DB::afterCommit(function () use ($user, $walletTransaction, $meta) {
            event(new WalletPurchaseCompleted(
                $user,
                $walletTransaction,
                $meta
            ));
        });

        return $walletTransaction;
    }
}
