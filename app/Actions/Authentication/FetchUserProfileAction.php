<?php

namespace App\Actions\Authentication;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\ExternalBankService;


class FetchUserProfileAction
{
    public static function execute(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load(['wallet', 'merchant']);

        if ($user->image) {
            $user->image_url = asset($user->image);
        }

        $wallet = $user->wallet;

        if ($wallet) {
            $wallet->pending_balance = $wallet->pending_balance ?? "0.00";

            // Default fallback
            $wallet->external_available_balance = 0.00;
            $wallet->external_book_balance      = 0.00;

            // Fetch real-time balances from Shanono Bank
            try {
                if ($wallet->external_account_number) {
                    $externalBalances = app(ExternalBankService::class)
                        ->getAccountBalance($wallet->external_account_number);

                    $wallet->external_available_balance = number_format(
                        $externalBalances['available_balance'],
                        2,
                        '.',
                        ''
                    );

                    $wallet->external_book_balance = number_format(
                        $externalBalances['book_balance'],
                        2,
                        '.',
                        ''
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch Shanono balance (profile)', [
                    'wallet_id' => $wallet->id,
                    'account'   => $wallet->external_account_number,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return [
            'user' => $user,
            'merchant_code' => $user->merchant?->merchant_code,
        ];
    }
}
