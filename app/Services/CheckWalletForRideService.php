<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletGuardService;
use App\Services\ExternalBankService;

class CheckWalletForRideService
{
    public function validateWallet(User $user, float $amount, ExternalBankService $bankService): Wallet
    {
        $walletGuard = app(WalletGuardService::class);

        // Ensure user has an external subaccount
        $wallet = $user->wallet;
        $walletGuard->ensureExternalAccountActive($wallet, $bankService);

        // Ensure user internal wallet balance
        $walletGuard->ensureCanSpend($user, $amount);

        // Ensure merchant liquidity
        $walletGuard->ensureMerchantLiquidity($bankService, $amount);

        return $wallet;
    }
}
