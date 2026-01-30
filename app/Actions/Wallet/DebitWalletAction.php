<?php

namespace App\Actions\Wallet;

use Throwable;
use App\Models\Wallet;
use Illuminate\Support\Str;
use App\Mail\WalletDebitedMail;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\DTOs\Wallet\DebitWalletDTO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\TransactionPinService;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;

class DebitWalletAction
{
    public function execute(DebitWalletDTO $dto, TwilioService $twilio, TermiiService $termii): array
    {

        app(TransactionPinService::class)->checkPin(Auth::user(), $dto->pin);


        $result = DB::transaction(function () use ($dto) {
            $wallet = Wallet::where('account_number', $dto->account_number)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->available_balance < $dto->amount) {
                throw new \Exception('Insufficient funds');
            }

            $wallet->available_balance -= $dto->amount;
            $wallet->total_balance     -= $dto->amount;
            $wallet->save();

            $transaction = WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $wallet->user_id,
                'type'        => WalletTransactionTypeEnums::DEBIT,
                'amount'      => $dto->amount,
                'description' => $dto->description ?? 'Wallet Debit',
                'reference'   => Str::uuid(),
                'status'      => WalletTransactionStatusEnums::SUCCESS,
                'method'      => $dto->method ?? WalletTransactionMethodEnums::MANUAL,
            ]);

            return compact('wallet', 'transaction');
        });

        $user = $result['wallet']->user;

        try {
            if ($user->email) {
                Mail::to($user->email)->send(new WalletDebitedMail($user, $dto->amount, $result['transaction']));
            }

            if ($user->phone) {
                $termii->sendSms(
                    $user->phone,
                    "Hello {$user->first_name}, your LoopFreight wallet has been debited with ₦" . number_format($dto->amount, 2) . "."
                );
            }

            if ($user->whatsapp_number) {
                $twilio->sendWhatsAppMessage(
                    $user->whatsapp_number,
                    "Hi {$user->first_name}, your LoopFreight wallet was debited with ₦" . number_format($dto->amount, 2) . ". Ref: {$result['transaction']->reference}"
                );
            }
        } catch (Throwable $e) {
            logError("Wallet debit notifications failed", $e);
        }

        return $result;
    }
}
