<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Payout;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\DTOs\Payout\PayoutDTO;
use App\Mail\WalletDebitedMail;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\PayoutStatusEnums;
use Illuminate\Support\Facades\DB;
use App\DTOs\Payout\ListPayoutsDTO;
use Illuminate\Support\Facades\Log;
use App\Actions\Payout\PayoutAction;
use App\Http\Controllers\Controller;
use App\Services\WalletGuardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\ExternalBankService;
use App\Http\Resources\PayoutResource;
use App\Services\TransactionPinService;
use App\Actions\Payout\ListPayoutsAction;
use App\Services\UserBankProfileResolver;
use GuzzleHttp\Exception\ConnectException;
use App\Services\PayoutWalletPurchaseService;
use App\Notifications\WalletDebitNotification;

class PayoutController extends Controller
{
    public function requestPayout(Request $request, ExternalBankService $bankService)
    {
        $user = Auth::user();
        if ($user->payout_restricted) {
            return failureResponse(
                'Payouts are currently restricted for this account.',
                403,
                'payout_restricted'
            );
        }

        try {
            $request->validate([
                'amount'          => 'required|numeric|min:100|max:10000000',
                'narration'       => 'required|string|max:100',
                'transaction_pin' => 'required|digits:4',
                'account_source'  => 'nullable|in:user,role',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse($e->errors(), 422, 'validation_error', $e);
        }

        try {
            app(TransactionPinService::class)->checkPin($user, $request->transaction_pin);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 403, 'invalid_transaction_pin', $e);
        }

        try {
            /**
             * WALLET + MERCHANT CHECKS
             */

            $walletGuard = app(WalletGuardService::class);

            $wallet = $walletGuard->ensureCanSpend($user, (float)$request->amount);

            Log::info('Sub-account fetched', [
                'account_number' => $wallet->external_account_number,
                'account_id'     => $wallet->external_account_id,
            ]);

            // Check merchant liquidity from Shanono API
            // Fetch merchant balance from Shanono
            $merchantBalance = $bankService->getMerchantBalance();
            Log::info('Merchant balance raw', $merchantBalance);

            $availableBalance = $merchantBalance['available_balance'] ?? 0;

            if (($merchantBalance['status'] ?? null) !== 'active' || $availableBalance < $request->amount) {
                Log::error('Insufficient merchant liquidity', [
                    'available_balance' => $availableBalance,
                    'transfer_amount'   => $request->amount,
                ]);
                throw new \RuntimeException('Merchant has insufficient liquidity');
            }

            Log::info('Merchant liquidity check passed', [
                'available_balance' => $availableBalance,
                'transfer_amount'   => $request->amount,
            ]);

            $merchant = $bankService->getMerchantBalance();

            $merchantAccountId = $merchant['account_id'];


            if (!$merchantAccountId) {
                throw new \RuntimeException('Merchant-account ID not found for this user.');
            }

            /**
             * BANK PROFILE RESOLUTION
             */
            $bankResolver = app(UserBankProfileResolver::class);
            $bankProfile  = $bankResolver->resolve($user);
            
            // if ($bankProfile['requires_choice']) {
            //     return successResponse('Select payout account', [
            //         'requires_choice' => true,
            //         'accounts'        => $bankProfile['accounts'],
            //     ]);
            // }

            $beneficiary = $bankProfile['selected'];

             if (! $beneficiary) {
                return failureResponse(
                    'Bank details not found. Please update your bank information.',
                    422,
                    'bank_details_missing'
                );
            }

            DB::beginTransaction();

            /**
             *EXECUTE TRANSFER
             */

            try {
                $transaction = $bankService->payoutToBank([
                    'account_id'                 => $merchantAccountId,
                    'beneficiary_account_name'   => $beneficiary['account_name'],
                    'beneficiary_account_number' => $beneficiary['account_number'],
                    'beneficiary_bank_code'      => $beneficiary['bank_code'],
                    'beneficiary_bank_name'      => $beneficiary['bank_name'],
                    'amount'                     => (float)$request->amount,
                    'narration'                  => $request->narration,
                    'idempotency_key'            => (string) Str::uuid(),
                ]);

                $status = 'processing';
            } catch (ConnectException $e) {
                //BANK TIMEOUT / NETWORK ISSUE
                $transaction = [
                    'status'    => 'pending',
                    'reference' => (string) Str::uuid(),
                    'provider'  => 'shanono',
                ];

                $status = 'pending';
            }

            Log::info('Payout executed', [
                'transaction' => $transaction,
            ]);

            $payout = Payout::create([
                'user_id'           => $user->id,
                'amount'            => (float) $request->amount,
                'bank_name'         => $beneficiary['bank_name'],
                'account_number'    => $beneficiary['account_number'],
                'currency'          => 'NGN',
                'status'            => match ($status) {
                    'processing' => PayoutStatusEnums::PROCESSING,
                    'pending'    => PayoutStatusEnums::PENDING,
                    default      => PayoutStatusEnums::FAILED,
                },
                'provider_reference' => $transaction['reference'] ?? null,
            ]);

            // Attach role-specific payout ownership
            if ($user->driver) {
                $payout->update(['driver_id' => $user->driver->id]);
            }

            if ($user->partner) {
                $payout->update(['partner_id' => $user->partner->id]);
            }

            if ($user->investor) {
                $payout->update(['investor_id' => $user->investor->id]);
            }

            /**
             * WALLET DEDUCTION
             */
            app(PayoutWalletPurchaseService::class)->process(
                user: $user,
                wallet: $wallet,
                amount: (float)$request->amount,
                method: 'bank_transfer',
                providerCallback: function ($reference) use ($transaction) {
                    return [
                        [
                            'responseCode' => $transaction['status'] === 'processing'
                                ? 202   // accepted / processing
                                : 200,  // completed immediately
                            'reference' => $reference,
                            'provider'  => 'shanono',
                        ]
                    ];
                },

                meta: [
                    'beneficiary' => $beneficiary,
                ]
            );

            DB::commit();

            /**
             * NOTIFICATIONS
             */
            $summary = "₦" . number_format($request->amount, 2) .
                " sent to {$beneficiary['bank_name']} ({$beneficiary['account_number']}). " .
                "Ref: {$transaction['reference']}";

            $user->notify(new WalletDebitNotification(
                amount: (float)$request->amount,
                reference: $transaction['reference'],
                summary: $summary,
                description: $request->narration
            ));

            // Email notification
            if ($user->email && $user->email_verified_at) {
                Mail::to($user->email)->queue(new WalletDebitedMail(
                    $user,
                    (float)$request->amount,
                    $transaction,
                    $summary
                ));
            }

            // SMS / WhatsApp notification
            if (!empty($user->phone) && !is_null($user->phone_verified_at)) {
                $message = $summary;

                try {
                    $termii = app(TermiiService::class);
                    $sms    = $termii->sendSms($user->phone, $message);

                    if (($sms['status'] ?? '') !== 'success') {
                        app(TwilioService::class)->sendWhatsappMessage($user->whatsapp_number, $message);
                    }
                } catch (\Throwable $e) {
                    Log::warning('SMS/WhatsApp notification failed', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return successResponse('Transfer initiated. Processing may take a few minutes.', $transaction);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Transfer failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return failureResponse('Transfer failed', 500, 'shanono_transfer_failed', $e);
        }
    }


    public function restrictPayouts(User $user)
    {

        $user->update([
            'payout_restricted' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User payout has been restricted',
        ]);
    }

    public function unrestrictPayouts(User $user)
    {

        $user->update([
            'payout_restricted' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User payout restriction removed',
        ]);
    }

    public function checkUserPayoutStatus(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'payout_restricted' => (bool) $user->payout_restricted,
                'status' => $user->payout_restricted
                    ? 'restricted'
                    : 'unrestricted',
            ],
        ]);
    }


    public function restrictAll(Request $request)
    {
        try {

            $affected = User::query()
                ->whereDoesntHave('roles', function ($q) {
                    $q->whereIn('name', ['super-admin', 'admin']);
                })
                ->update([
                    'payout_restricted' => true,
                ]);

            return successResponse(
                'All users have been restricted from payouts',
                [
                    'affected_users' => $affected,
                ]
            );
        } catch (\Throwable $e) {
            return failureResponse(
                'Failed to restrict payouts for all users',
                500,
                'GLOBAL_PAYOUT_RESTRICT_FAILED',
                $e
            );
        }
    }

    public function unrestrictAll(Request $request)
    {
        try {

            $affected = User::query()
                ->where('payout_restricted', true)
                ->update([
                    'payout_restricted' => false,
                ]);

            return successResponse(
                'All users have been unrestricted from payouts',
                [
                    'affected_users' => $affected,
                ]
            );
        } catch (\Throwable $e) {
            return failureResponse(
                'Failed to unrestrict payouts for all users',
                500,
                'GLOBAL_PAYOUT_UNRESTRICT_FAILED',
                $e
            );
        }
    }

    public function checkGlobalPayoutStatus()
    {
        $totalUsers = User::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['super-admin', 'admin']);
            })
            ->count();

        $restrictedUsers = User::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['super-admin', 'admin']);
            })
            ->where('payout_restricted', true)
            ->count();

        $allRestricted = $totalUsers > 0 && $restrictedUsers === $totalUsers;

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'restricted_users' => $restrictedUsers,
                'all_users_restricted' => $allRestricted,
                'status' => $allRestricted
                    ? 'all_restricted'
                    : 'partially_or_not_restricted',
            ],
        ]);
    }



    public function index(Request $request, ListPayoutsAction $action)
    {
        $dto = ListPayoutsDTO::fromRequest($request);

        $payouts = $action->execute($dto);

        return successResponse(
            "Payouts retrieved successfully",
            PayoutResource::collection($payouts)->response()->getData(true)
        );
    }
}
