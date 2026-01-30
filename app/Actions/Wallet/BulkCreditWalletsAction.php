<?php

namespace App\Actions\Wallet;

use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Models\WalletTransaction;
use App\DTOs\Wallet\BulkCreditDTO;
use App\Models\PendingWalletDebit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\TransactionPinService;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Notifications\User\UserWalletCreditedNotification;

class BulkCreditWalletsAction
{
    public function execute(BulkCreditDTO $dto): void
    {
        app(TransactionPinService::class)->checkPin(Auth::user(), $dto->pin);


        $wallets = Wallet::with('user')->get();

        DB::transaction(function () use ($wallets, $dto) {
            foreach ($wallets as $wallet) {
                $wallet->available_balance += $dto->amount;
                $wallet->total_balance     += $dto->amount;
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id'   => $wallet->id,
                    'user_id'     => $wallet->user_id,
                    'type'        => WalletTransactionTypeEnums::CREDIT,
                    'amount'      => $dto->amount,
                    'description' => $dto->description ?? 'Bulk Credit',
                    'reference'   => Str::uuid(),
                    'status'      => WalletTransactionStatusEnums::SUCCESS,
                    'method'      => $dto->method ?? WalletTransactionMethodEnums::MANUAL,
                ]);

                $this->processPendingDebits($wallet);

                if ($wallet->user) {
                    $wallet->user->notify(
                        new UserWalletCreditedNotification(
                            $dto->amount,
                            $dto->description
                        )
                    );
                }
            }
        });
    }

    private function processPendingDebits(Wallet $wallet)
    {
        $pendingDebits = PendingWalletDebit::where('wallet_id', $wallet->id)->get();

        foreach ($pendingDebits as $pending) {
            if ($wallet->available_balance >= $pending->amount) {
                $wallet->available_balance -= $pending->amount;
                $wallet->total_balance     -= $pending->amount;
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id'   => $wallet->id,
                    'user_id'     => $wallet->user_id,
                    'type'        => WalletTransactionTypeEnums::DEBIT,
                    'amount'      => $pending->amount,
                    'description' => $pending->description,
                    'reference'   => $pending->reference,
                    'status'      => WalletTransactionStatusEnums::SUCCESS,
                    'method'      => $pending->method,
                ]);

                $pending->delete();
            }
        }
    }
}
