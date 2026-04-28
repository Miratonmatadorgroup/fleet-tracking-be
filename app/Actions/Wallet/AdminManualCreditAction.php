<?php

namespace App\Actions\Wallet;

use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\DTOs\Wallet\CreditWalletDTO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\TransactionPinService;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;

class AdminManualCreditAction
{
    // public function execute(CreditWalletDTO $dto, TwilioService $twilio, TermiiService $termii): array
    // {

    //     Log::info('ACTION RECEIVED DTO', [
    //         'account_number' => $dto->account_number,
    //         'amount' => $dto->amount,
    //         'transaction_pin' => $dto->pin
    //     ]);

    //     if (!$dto->pin) {
    //         throw new \Exception("Transaction PIN missing from request");
    //     }

    //     app(TransactionPinService::class)->checkPin(Auth::user(), $dto->pin);

    //     $result = DB::transaction(function () use ($dto) {

    //         $wallet = Wallet::where('account_number', $dto->account_number)
    //             ->lockForUpdate()
    //             ->firstOrFail();

    //         // Credit directly without touching admin wallet
    //         $wallet->available_balance += $dto->amount;
    //         $wallet->total_balance     += $dto->amount;
    //         $wallet->save();

    //         $transaction = WalletTransaction::create([
    //             'wallet_id'   => $wallet->id,
    //             'user_id'     => $wallet->user_id,
    //             'type'        => WalletTransactionTypeEnums::CREDIT,
    //             'amount'      => $dto->amount,
    //             'description' => $dto->description ?? 'Admin Manual Credit Adjustment',
    //             'reference'   => Str::uuid(),
    //             'status'      => WalletTransactionStatusEnums::SUCCESS,
    //             'method'      => WalletTransactionMethodEnums::MANUAL,
    //         ]);

    //         return compact('wallet', 'transaction');
    //     });

    //     $user = $result['wallet']->user;

    //     try {
    //         if ($user->email) {
    //             Mail::to($user->email)->send(new WalletCreditedMail($user, $dto->amount, $result['transaction']));
    //         }

    //         if ($user->phone) {
    //             $termii->sendSms(
    //                 $user->phone,
    //                 "Hello {$user->name}, your LoopFreight wallet has been credited with ₦" . number_format($dto->amount, 2) . "."
    //             );
    //         }

    //         if ($user->whatsapp_number) {
    //             $twilio->sendWhatsAppMessage(
    //                 $user->whatsapp_number,
    //                 "Hi {$user->name}, your LoopFreight wallet was credited with ₦" . number_format($dto->amount, 2) . ". Ref: {$result['transaction']->reference}"
    //             );
    //         }
    //     } catch (Throwable $e) {
    //         logError("Wallet credit notifications failed", $e);
    //     }

    //     return $result;
    // }

    public function execute(CreditWalletDTO $dto): array
    {
        Log::info('ACTION RECEIVED DTO', [
            'account_number' => $dto->account_number,
            'amount' => $dto->amount,
            'transaction_pin' => $dto->pin
        ]);

        if (!$dto->pin) {
            throw new \Exception("Transaction PIN missing from request");
        }

        $admin = Auth::user();

        app(TransactionPinService::class)->checkPin($admin, $dto->pin);

        $result = DB::transaction(function () use ($dto) {

            $wallet = Wallet::where('account_number', $dto->account_number)
                ->lockForUpdate()
                ->firstOrFail();

            // Credit directly
            $wallet->increment('available_balance', $dto->amount);
            $wallet->increment('total_balance', $dto->amount);

            $reference = Str::uuid();

            $transaction = WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $wallet->user_id,
                'type'        => WalletTransactionTypeEnums::CREDIT,
                'amount'      => $dto->amount,
                'description' => $dto->description ?? 'Admin Manual Credit Adjustment',
                'reference'   => $reference,
                'status'      => WalletTransactionStatusEnums::SUCCESS,
                'method'      => WalletTransactionMethodEnums::MANUAL,
            ]);

            return [
                'wallet' => $wallet,
                'transaction' => $transaction,
                'reference' => $reference,
                'sender' => Auth::user(),
            ];
        });

        return $result;
    }
}
