<?php

namespace App\Http\Controllers\Api;


use Throwable;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\WalletDebitedMail;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Validation\Rule;
use App\DTOs\Wallet\BulkDebitDTO;
use App\Models\WalletTransaction;
use App\Services\PaystackService;
use App\DTOs\Wallet\BulkCreditDTO;
use App\Models\PendingWalletDebit;
use Illuminate\Support\Facades\DB;
use App\DTOs\Wallet\DebitWalletDTO;
use Illuminate\Support\Facades\Log;
use App\DTOs\Wallet\CreditWalletDTO;
use App\Http\Controllers\Controller;
use App\Services\WalletGuardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\DataPurchaseReceiptMail;
use App\Services\ExternalBankService;
use Illuminate\Support\Facades\Cache;
use App\Services\TransactionPinService;
use App\Services\WalletPurchaseService;
use App\DTOs\Wallet\UserTransactionsDTO;
use App\Mail\AirtimePurchaseReceiptMail;
use App\Actions\Wallet\DebitWalletAction;
use App\Enums\WalletTransactionTypeEnums;
use App\Actions\Wallet\CreditWalletAction;
use GuzzleHttp\Exception\ConnectException;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Services\PayoutWalletPurchaseService;
use App\Actions\Wallet\BulkDebitWalletsAction;
use App\Notifications\WalletDebitNotification;
use App\Actions\Wallet\BulkCreditWalletsAction;
use App\Actions\Wallet\GetUserTransactionsAction;
use App\Services\BillsPayment\DataPurchaseService;
use App\Notifications\User\DataPurchaseNotification;
use App\Services\BillsPayment\AirtimePurchaseService;
use App\Notifications\User\AirtimePurchaseNotification;
use App\Services\BillsPayment\ElectricityPurchaseService;



class WalletTransactionController extends Controller
{
    public function adminCredit(
        Request $request,
        TwilioService $twilio,
        TermiiService $termii,
        CreditWalletAction $action
    ) {

        Log::info('RAW INPUT', $request->all());
        Log::info('HEADERS', $request->headers->all());

        $validated = $request->validate([
            'account_number' => 'required|exists:wallets,account_number',
            'amount'         => 'required|numeric|min:0.01',
            'description'    => 'nullable|string',
            'method'         => 'nullable|string',
            'transaction_pin' => 'required|size:4',

        ]);
        Log::info('VALIDATED DATA', $validated);


        try {
            $dto = new CreditWalletDTO($validated);
            Log::info('DTO PIN', ['transaction_pin' => $dto->pin]);
            $result = $action->execute($dto, $twilio, $termii);

            return successResponse('Wallet credited successfully.', $result);
        } catch (\Throwable $th) {
            return failureResponse('Failed to credit wallet.', 500, 'wallet_credit_error', $th);
        }
    }

    public function adminDebit(
        Request $request,
        TwilioService $twilio,
        TermiiService $termii,
        DebitWalletAction $action
    ) {
        $validated = $request->validate([
            'account_number' => 'required|exists:wallets,account_number',
            'amount'         => 'required|numeric|min:0.01',
            'description'    => 'nullable|string',
            'method'         => 'nullable|string',
            'transaction_pin' => 'required|size:4',

        ]);

        try {
            $dto = new DebitWalletDTO($validated);
            $result = $action->execute($dto, $twilio, $termii);

            return successResponse('Wallet debited successfully.', $result);
        } catch (\Throwable $th) {
            $message = $th->getMessage() === 'Insufficient funds'
                ? 'Insufficient funds.'
                : 'Failed to debit wallet.';

            return failureResponse($message, 422, 'wallet_error', $th);
        }
    }


    public function bulkCredit(Request $request, BulkCreditWalletsAction $action)
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'method'      => 'nullable|string',
            'transaction_pin'         => 'required|size:4',

        ]);

        try {
            $dto = new BulkCreditDTO($validated);
            $action->execute($dto);

            return successResponse('Bulk credit applied to all wallets.');
        } catch (\Throwable $th) {
            return failureResponse('Failed to bulk credit wallets.', 500, 'bulk_credit_error', $th);
        }
    }

    public function bulkDebit(Request $request, BulkDebitWalletsAction $action)
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'method'      => 'nullable|string',
            'transaction_pin' => 'required|size:4',

        ]);

        try {
            $dto = new BulkDebitDTO($validated);
            $failedUsers = $action->execute($dto);

            $message = 'Bulk debit completed.';
            if ($failedUsers) {
                $message .= ' Some wallets had insufficient funds.';
            }

            return successResponse($message, [
                'failed_user_ids' => $failedUsers,
            ]);
        } catch (\Throwable $th) {
            return failureResponse('Failed to process bulk debit.', 500, 'bulk_debit_error', $th);
        }
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

    public function userTransactions(Request $request, GetUserTransactionsAction $transactionsAction)
    {
        try {
            $dto = UserTransactionsDTO::fromAuth();
            $perPage = $request->get('per_page', 10);

            $transactions = $transactionsAction->execute($dto, $perPage);

            return successResponse(
                "User wallet transactions retrieved successfully.",
                $transactions
            );
        } catch (\Throwable $th) {
            return failureResponse(
                "Failed to fetch user wallet transactions.",
                500,
                'user_wallet_transactions_error',
                $th
            );
        }
    }

    public function shanonoWalletTransactions(
        Request $request,
        ExternalBankService $bankService
    ) {
        try {
            $validated = $request->validate([
                'page'     => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:50',
            ]);

            $merchantAccountNumber = config('services.shanono_bank.account_number');

            if (!$merchantAccountNumber) {
                return failureResponse(
                    'Merchant wallet account is not configured',
                    500,
                    'merchant_wallet_missing'
                );
            }

            $balance = $bankService->getMerchantBalance();

            if (($balance['status'] ?? null) !== 'active') {
                return failureResponse(
                    'Merchant wallet is inactive',
                    403,
                    'merchant_wallet_inactive'
                );
            }

            $transactions = $bankService->getMerchantTransactions(
                $validated['page'] ?? 1,
                $validated['per_page'] ?? 20
            );


            return successResponse(
                'Merchant wallet transactions retrieved successfully',
                [
                    'account'      => $transactions['account'],
                    'transactions' => $transactions['transactions'],
                    'pagination'   => $transactions['pagination'],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Merchant wallet transaction error', [
                'error' => $e->getMessage(),
            ]);

            return failureResponse(
                'Unable to fetch merchant wallet transactions',
                500,
                'shanono_transactions_error'
            );
        }
    }

    // BILLS PAYMENT FUNCTION STARTS HERE
    // USERS BUY ARTIME STARTS HERE
    public function airtimeProviders(ExternalBankService $bankService)
    {
        try {
            $providers = $bankService->getAirtimeProviders();

            return successResponse(
                'Airtime providers retrieved',
                $providers
            );
        } catch (\Throwable $e) {
            return failureResponse(
                'Unable to fetch airtime providers',
                500,
                'provider_fetch_failed',
                $e
            );
        }
    }

    public function buyAirtime(Request $request, ExternalBankService $bankService, WalletGuardService $guard, AirtimePurchaseService $purchase,)
    {
        $user = Auth::user();

        $request->validate([
            'phone'    => 'required|string',
            'provider' => 'required|string',
            'amount'   => 'required|numeric|min:50',
            'transaction_pin' => 'required|string',
        ]);

        app(TransactionPinService::class)->checkPin(
            $user,
            $request->transaction_pin
        );

        $wallet = $guard->ensureCanSpend($user, $request->amount);
        $guard->ensureMerchantLiquidity($bankService, $request->amount);
        $guard->ensureExternalAccountActive($wallet, $bankService);

        $transaction = $purchase->process(
            $user,
            $wallet,
            $request->amount,
            WalletTransactionMethodEnums::AIRTIME,
            fn($reference) => $bankService->purchaseAirtime([
                'phone'    => $request->phone,
                'provider' => $request->provider,
                'amount'   => $request->amount,
            ]),
            [
                'phone'    => $request->phone,
                'provider' => $request->provider,
            ]
        );

        return successResponse('Airtime purchase successful', [
            'transaction' => $transaction,
            'balance' => $wallet->fresh()->available_balance,
        ]);
    }
    // USERS BUY AIRTIME ENDS HERE


    // USER BUY DATA STARTS HERE
    public function dataPlans(Request $request, ExternalBankService $bankService)
    {
        try {
            $provider = $request->query('provider')
                ?? $request->input('provider');

            $plans = $bankService->getDataPlans($provider);

            return successResponse(
                'Data plans retrieved successfully',
                $plans
            );
        } catch (\Throwable $e) {
            Log::error('Failed to fetch data plans', [
                'error' => $e->getMessage(),
            ]);

            return failureResponse(
                'Unable to fetch data plans',
                500,
                'data_plan_fetch_failed',
                $e
            );
        }
    }

    public function buyData(Request $request, ExternalBankService $bankService, WalletGuardService $guard, DataPurchaseService $purchase)
    {
        $user = Auth::user();

        $request->validate([
            'phone'           => 'required|string|min:10|max:15',
            'provider'        => 'required|string|in:MTN,GLO,AIRTEL,9MOBILE',
            'plan_code'       => 'required|string',
            'transaction_pin' => 'required|string|min:4|max:6',
        ]);

        app(TransactionPinService::class)->checkPin(
            $user,
            $request->transaction_pin
        );

        $plans = $bankService->getDataPlans($request->provider);

        $selectedPlan = collect($plans)->firstWhere(
            'code',
            $request->plan_code
        );

        if (!$selectedPlan) {
            return failureResponse(
                'Invalid data plan selected',
                422,
                'invalid_data_plan'
            );
        }

        $amount = (float) $selectedPlan['price'];

        $wallet = $guard->ensureCanSpend($user, $amount);
        $guard->ensureMerchantLiquidity($bankService, $amount);
        $guard->ensureExternalAccountActive($wallet, $bankService);

        $transaction = $purchase->process(
            $user,
            $wallet,
            $amount,
            WalletTransactionMethodEnums::DATA,
            fn($reference) => $bankService->purchaseData([
                'phone'        => $request->phone,
                'provider'     => $request->provider,
                'amount'       => $amount,
                'tariff_class' => $selectedPlan['code'],
            ]),
            [
                'phone'       => $request->phone,
                'provider'    => $request->provider,
                'plan_code'   => $selectedPlan['code'],
                'plan_desc'   => $selectedPlan['desc'],
                'duration'    => $selectedPlan['duration'],
                'time_unit'   => $selectedPlan['timeUnit'],
            ]
        );

        return successResponse(
            $transaction->status === WalletTransactionStatusEnums::PENDING
                ? 'Data purchase pending'
                : 'Data purchase successful',
            [
                'transaction'       => $transaction,
                'available_balance' => $wallet->fresh()->available_balance,
                'pending_balance'   => $wallet->fresh()->pending_balance,
            ]
        );
    }
    // USER BUY DATAT ENDS HERE


    // USER BUY ELECTRICITY STARTS HERE
    public function electricityProviders(ExternalBankService $bankService)
    {
        try {
            $providers = $bankService->getElectricityProviders();

            return successResponse(
                'Electricity providers retrieved',
                $providers
            );
        } catch (\Throwable $e) {
            return failureResponse(
                'Unable to fetch electricity providers',
                500,
                'provider_fetch_failed',
                $e
            );
        }
    }
    public function buyElectricity(Request $request, ExternalBankService $bankService, WalletGuardService $guard, ElectricityPurchaseService $purchase)
    {
        $user = Auth::user();

        $request->validate([
            'meter'           => 'required|string|min:6',
            'disco'           => 'required|string',
            'vend_type'       => 'required|string|in:PREPAID,POSTPAID',
            'amount'          => 'required|numeric|min:100',
            'transaction_pin' => 'required|string|min:4|max:6',
            'phone'           => 'required|string|min:10|max:15',
        ]);

        app(TransactionPinService::class)->checkPin(
            $user,
            $request->transaction_pin
        );

        $amount = (float) $request->amount;

        $wallet = $guard->ensureCanSpend($user, $amount);
        $guard->ensureMerchantLiquidity($bankService, $amount);
        $guard->ensureExternalAccountActive($wallet, $bankService);

        $transaction = $purchase->process(
            $user,
            $wallet,
            $amount,
            WalletTransactionMethodEnums::ELECTRICITY,
            fn() => $bankService->purchaseElectricity([
                'meter'     => $request->meter,
                'disco'     => $request->disco,
                'vend_type' => $request->vend_type,
                'amount'    => $amount,
                'phone'     => $request->phone,
            ]),
            // THIS META POWERS YOUR NOTIFICATIONS
            [
                'meter'     => $request->meter,
                'disco'     => $request->disco,
                'vend_type' => $request->vend_type,
                'phone'     => $request->phone,
            ]
        );

        return successResponse(
            $transaction->status === WalletTransactionStatusEnums::PENDING
                ? 'Electricity purchase pending'
                : 'Electricity purchase successful',
            [
                'transaction'       => $transaction,
                'available_balance' => $wallet->fresh()->available_balance,
                'pending_balance'   => $wallet->fresh()->pending_balance,
            ]
        );
    }
    // USER BUY ELECTRICITY ENDS HERE

    //USER  SUB CABLE TV STARTS HERE
    public function cabletvProviders(ExternalBankService $bankService)
    {
        try {
            $providers = $bankService->getCableTvProviders();

            return successResponse(
                'CableTv providers retrieved',
                $providers
            );
        } catch (\Throwable $e) {
            return failureResponse(
                'Unable to fetch cabletv providers',
                500,
                'provider_fetch_failed',
                $e
            );
        }
    }

    public function buyTvSubscription(Request $request, ExternalBankService $bankService, WalletGuardService $guard, WalletPurchaseService $purchase)
    {
        $user = Auth::user();

        $request->validate([
            'decoder_number'  => 'required|string|min:6',
            'disco'           => 'required|string|in:DSTV,GOTV,STARTIMES',
            'tariff_class'    => 'required|string',
            'amount'          => 'required|numeric|min:100',
            'transaction_pin' => 'required|string|min:4|max:6',
        ]);

        app(TransactionPinService::class)->checkPin(
            $user,
            $request->transaction_pin
        );

        $amount = (float) $request->amount;

        $wallet = $guard->ensureCanSpend($user, $amount);
        $guard->ensureMerchantLiquidity($bankService, $amount);
        $guard->ensureExternalAccountActive($wallet, $bankService);

        $transaction = $purchase->process(
            $user,
            $wallet,
            $amount,
            WalletTransactionMethodEnums::CABLETV,
            fn() => $bankService->purchaseTvSubscription([
                'decoder_number' => $request->decoder_number,
                'disco'          => $request->disco,
                'tariff_class'   => $request->tariff_class,
                'amount'         => $amount,
            ]),
            // META (for notifications & records)
            [
                'decoder_number' => $request->decoder_number,
                'provider'       => $request->disco,
                'package'        => $request->tariff_class,
                'vend_type'      => 'cable_tv',
            ]
        );

        return successResponse(
            $transaction->status === WalletTransactionStatusEnums::PENDING
                ? 'TV subscription pending'
                : 'TV subscription successful',
            [
                'transaction'       => $transaction,
                'available_balance' => $wallet->fresh()->available_balance,
                'pending_balance'   => $wallet->fresh()->pending_balance,
            ]
        );
    }

    // USER SUB CABLE TV ENDS HERE

    // BILLS PAYMENT COMMISSIONS

    // BILLS PAYMENT FUNCTIONS ENDS HERE
    public function viewBillsCommissions(
        Request $request,
        ExternalBankService $externalBankService
    ) {
        try {
            $filters = $request->validate([
                'start_date'        => ['nullable', 'date_format:Y-m-d'],
                'end_date'          => ['nullable', 'date_format:Y-m-d'],
                'bill_payment_type' => ['nullable', 'in:airtime,data,electricity,tv'],
                'page'              => ['nullable', 'integer', 'min:1'],
                'per_page'          => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $data = $externalBankService->getCommissionTransactions($filters);

            return successResponse(
                'Bills commission transactions retrieved successfully',
                $data
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse(
                $e->errors(),
                422,
                'validation_error',
                $e
            );
        } catch (\Throwable $e) {
            Log::error('Bills commission fetch failed', [
                'error' => $e->getMessage(),
            ]);

            return failureResponse(
                'Unable to fetch bills commissions',
                500,
                'shanono_commission_error',
                $e
            );
        }
    }
}
