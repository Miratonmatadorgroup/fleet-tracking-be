<?php

namespace App\Actions\Wallet;

use Throwable;
use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Mail\WalletCreditedMail;
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

class CreditWalletAction
{
    public function execute(CreditWalletDTO $dto, TwilioService $twilio, TermiiService $termii): array
    {

        Log::info('ACTION RECEIVED DTO', [
            'account_number' => $dto->account_number,
            'amount' => $dto->amount,
            'transaction_pin' => $dto->pin
        ]);
        if (!$dto->pin) {
            Log::error("TRANSACTION PIN IS MISSING IN DTO");
            throw new \Exception("Transaction PIN missing from request");
        }

        app(TransactionPinService::class)->checkPin(Auth::user(), $dto->pin);


        $result = DB::transaction(function () use ($dto) {
            $wallet = Wallet::where('account_number', $dto->account_number)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->available_balance += $dto->amount;
            $wallet->total_balance     += $dto->amount;
            $wallet->save();

            $transaction = WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $wallet->user_id,
                'type'        => WalletTransactionTypeEnums::CREDIT,
                'amount'      => $dto->amount,
                'description' => $dto->description ?? 'Wallet Credit',
                'reference'   => Str::uuid(),
                'status'      => WalletTransactionStatusEnums::SUCCESS,
                'method'      => $dto->method ?? WalletTransactionMethodEnums::MANUAL,
            ]);

            return compact('wallet', 'transaction');
        });

        $user = $result['wallet']->user;

        try {
            if ($user->email) {
                Mail::to($user->email)->send(new WalletCreditedMail($user, $dto->amount, $result['transaction']));
            }

            if ($user->phone) {
                $termii->sendSms(
                    $user->phone,
                    "Hello {$user->first_name}, your LoopFreight wallet has been credited with ₦" . number_format($dto->amount, 2) . "."
                );
            }

            if ($user->whatsapp_number) {
                $twilio->sendWhatsAppMessage(
                    $user->whatsapp_number,
                    "Hi {$user->first_name}, your LoopFreight wallet was credited with ₦" . number_format($dto->amount, 2) . ". Ref: {$result['transaction']->reference}"
                );
            }
        } catch (Throwable $e) {
            logError("Wallet credit notifications failed", $e);
        }

        return $result;
    }
}
