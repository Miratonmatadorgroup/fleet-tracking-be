<?php

namespace App\Actions\Wallet;

use App\Models\Wallet;
use Illuminate\Support\Str;
use App\DTOs\Wallet\BulkDebitDTO;
use App\Models\WalletTransaction;
use App\Models\PendingWalletDebit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\TransactionPinService;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Notifications\User\UserWalletDebitedNotification;
use App\Notifications\User\PendingWalletDebitNotification;

class BulkDebitWalletsAction
{
    public function execute(BulkDebitDTO $dto): array
    {
        app(TransactionPinService::class)->checkPin(Auth::user(), $dto->pin);


        $failedUsers = [];

        DB::transaction(function () use ($dto, &$failedUsers) {
            $wallets = Wallet::with('user')->lockForUpdate()->get();

            foreach ($wallets as $wallet) {
                $user = $wallet->user;

                if ($wallet->available_balance >= $dto->amount) {
                    $wallet->available_balance -= $dto->amount;
                    $wallet->total_balance     -= $dto->amount;
                    $wallet->save();

                    WalletTransaction::create([
                        'wallet_id'   => $wallet->id,
                        'user_id'     => $wallet->user_id,
                        'type'        => WalletTransactionTypeEnums::DEBIT,
                        'amount'      => $dto->amount,
                        'description' => $dto->description ?? 'Bulk Debit',
                        'reference'   => Str::uuid(),
                        'status'      => WalletTransactionStatusEnums::SUCCESS,
                        'method'      => $dto->method ?? WalletTransactionMethodEnums::MANUAL,
                    ]);

                    if ($user) {
                        $user->notify(new UserWalletDebitedNotification(
                            $dto->amount,
                            $dto->description
                        ));
                    }
                } else {
                    PendingWalletDebit::create([
                        'id'          => Str::uuid(),
                        'wallet_id'   => $wallet->id,
                        'amount'      => $dto->amount,
                        'description' => $dto->description ?? 'Deferred Bulk Debit',
                        'method'      => $dto->method ?? WalletTransactionMethodEnums::MANUAL,
                        'reference'   => Str::uuid(),
                    ]);

                    $failedUsers[] = [
                        'user_id'           => $wallet->user_id,
                        'account_number'    => $wallet->account_number,
                        'available_balance' => $wallet->available_balance,
                    ];

                    if ($user) {
                        $user->notify(new PendingWalletDebitNotification(
                            $dto->amount,
                            $dto->description
                        ));
                    }
                }
            }
        });

        return $failedUsers;
    }
}
