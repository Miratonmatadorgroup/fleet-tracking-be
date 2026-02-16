<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\ExternalBankService;

class WalletGuardService
{
    public function ensureCanSpend(User $user, float $amount): Wallet
    {
        $wallet = $user->wallet;

        if (! $wallet) {
            throw new \Exception('Wallet not found');
        }

        if ($wallet->available_balance < $amount) {
            throw new \Exception('Insufficient wallet balance');
        }

        return $wallet;
    }

    public function ensureMerchantLiquidity(
        ExternalBankService $bankService,
        float $amount
    ): void {
        $merchant = $bankService->getMerchantBalance();

        if (
            ($merchant['status'] ?? null) !== 'active' ||
            ($merchant['available_balance'] ?? 0) < $amount
        ) {
            throw new \Exception('Merchant liquidity insufficient');
        }
    }

    public function ensureExternalAccountActive(Wallet $wallet, ExternalBankService $bankService): void
    {
        if (! $wallet->external_account_number) {
            throw new \Exception('Virtual account not configured');
        }

        $account = $bankService->getAccountBalance(
            $wallet->external_account_number
        );

        if (
            ($account['status'] ?? null) !== 'active' ||
            ! ($account['can_transfer'] ?? false)
        ) {
            throw new \Exception('Virtual account inactive');
        }
    }

    public function ensureExternalAccountDetailsActive(Wallet $wallet, ExternalBankService $bankService): array
    {
        if (! $wallet->external_account_number) {
            throw new \Exception('Virtual account not configured');
        }

        $account = $bankService->getAccountBalance(
            $wallet->external_account_number
        );

        if (
            ($account['status'] ?? null) !== 'active' ||
            ! ($account['can_transfer'] ?? false)
        ) {
            throw new \Exception('Virtual account inactive');
        }

        return $account;
    }
}
