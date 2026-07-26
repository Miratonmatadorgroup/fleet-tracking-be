<?php

namespace App\Actions\Authentication;

use App\DTOs\Authentication\LoginDTO;
use App\Events\Authentication\UserLoggedInEvent;
use App\Models\ApiClient;
use App\Models\User;
use App\Models\UserToken;
use App\Models\Wallet;
use App\Services\ExternalBankService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
                'status'  => 400,
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

        $apiClients = collect();

        if ($user->hasRole('dev')) {

            $clients = ApiClient::with('webhook')
                ->where('customer_id', $user->id)
                ->get();

            $apiClients = $clients->map(function ($client) {

                return [

                    'client_id' => $client->id,

                    'environment' => $client->environment,

                    'api_key' => $client->api_key,

                    'company_name' => $client->company_name,

                    'company_website' => $client->company_website,

                    'callback_url' => $client->callback_url,
                    'webhook' => optional($client->webhook)->webhook_url,
                    'webhook_secret' => optional($client->webhook)->webhook_secret,

                ];
            });
        }

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

        // SUBSCRIPTION LOGIC
        $subscription = $user->activeSubscription()
            ->with('plan')
            ->first();

        $subscriptionData = null;

        if ($subscription) {
            $subscriptionData = [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'is_active' => $subscription->isActive(),
                'days_until_expiry' => $subscription->daysUntilExpiry(),
                'auto_renew' => $subscription->auto_renew,
                'is_trial' => $subscription->is_trial,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name ?? null,
                ] : null,
            ];
        }

        // RETURN RESPONSE
        return [
            'error'        => false,
            'user'         => $user,
            'role'         => $role,
            'token'        => $accessToken,
            'wallet'       => $walletData,
            'subscription' => $subscriptionData,
            'api_clients' => $apiClients,
        ];
    }
}
