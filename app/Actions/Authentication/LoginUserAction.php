<?php

namespace App\Actions\Authentication;

use App\Models\User;
use App\Models\Wallet;
use App\Models\UserToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\DTOs\Authentication\LoginDTO;
use App\Services\ExternalBankService;
use Illuminate\Support\Facades\Response;
use App\Events\Authentication\UserLoggedInEvent;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginUserAction
{
    public static function execute(LoginDTO $dto): array
    {
        $user = User::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->first();

        if (!$user || !Hash::check($dto->password, $user->password)) {

            return [
                'error'   => true,
                'status'  => 401,
                'message' => 'Invalid credentials.'
            ];
        }

        // Create Passport token
        $tokenResult = $user->createToken('API Token');
        $accessToken = $tokenResult->accessToken;
        $passport = $tokenResult->token;

        // Save the SAME TOKEN ID
        UserToken::create([
            'id'            => $passport->id,
            'user_id'       => $user->id,
            'device_name'   => detectDeviceName(request()->userAgent()),
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'last_activity' => now(),
            'expires_at'    => now()->addDays(30),
        ]);

        event(new UserLoggedInEvent($user));

        $role = $user->getRoleNames()->first();

        $wallet = Wallet::where('user_id', $user->id)->first();
        $walletData = null;

        if ($wallet) {
            $walletData = $wallet->toArray();

            // Defaults (IMPORTANT)
            $walletData['external_available_balance'] = "0.00";
            $walletData['external_book_balance']      = "0.00";

            try {
                if ($wallet->external_account_number) {

                    $balances = app(ExternalBankService::class)
                        ->getAccountBalanceCached($wallet->external_account_number);

                    $walletData['external_available_balance'] = number_format(
                        $balances['available_balance'],
                        2,
                        '.',
                        ''
                    );

                    $walletData['external_book_balance'] = number_format(
                        $balances['book_balance'],
                        2,
                        '.',
                        ''
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch Shanono balance (login)', [
                    'wallet_id' => $wallet->id,
                    'account'   => $wallet->external_account_number,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return [
            'error'  => false,
            'user'   => $user,
            'role'   => $role,
            'token'  => $accessToken,
            'wallet' => $walletData,
        ];
    }
}
