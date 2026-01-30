<?php

namespace App\Actions\Payout;

use Exception;
use App\Models\User;
use App\Models\Driver;
use App\Models\Payout;
use App\Models\Wallet;
use App\Models\Partner;
use App\Models\Investor;
use Illuminate\Support\Str;
use App\DTOs\Payout\PayoutDTO;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\PayoutStatusEnums;
use App\Mail\PayoutInitiatedMail;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Mail;
use App\Notifications\PayoutInitiatedNotification;


class PayoutAction
{
    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {}

    public function execute(PayoutDTO $dto)
    {
        //Validate the user's transaction PIN
        app(\App\Services\TransactionPinService::class)->checkPin($dto->user, $dto->pin);

        $wallet = Wallet::where('user_id', $dto->user->id)->firstOrFail();

        if ($wallet->available_balance < $dto->amount) {
            throw new Exception("Insufficient wallet balance.");
        }

        // Deduct from balances
        $wallet->available_balance -= $dto->amount;
        $wallet->total_balance -= $dto->amount;
        $wallet->save();

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'user_id'     => $dto->user->id,
            'type'        => 'debit',
            'amount'      => $dto->amount,
            'description' => 'Payout request',
            'reference'   => Str::uuid(),
            'status'      => 'success',
            'method'      => 'wallet',
        ]);

        // Get bank details
        [$bankDetails, $payoutRelations] = $this->resolveBankDetails($dto->user);

        if (empty($bankDetails['bank_name']) || empty($bankDetails['account_number'])) {
            $role = $dto->user->getRoleNames()->first();
            throw new \Exception("No bank details found for this {$role}.");
        }

        $payout = Payout::create(array_merge([
            'user_id' => $dto->user->id,
            'amount' => $dto->amount,
            'bank_name' => $bankDetails['bank_name'],
            'account_number' => $bankDetails['account_number'],
            'currency' => $wallet->currency->value,
            'status' => PayoutStatusEnums::PENDING->value,
        ], $payoutRelations));

        $this->notifyUser($dto->user, $payout);

        return $payout;
    }


    private function resolveBankDetails(User $user): array
    {
        if ($user->hasRole('driver')) {
            $driver = Driver::where('user_id', $user->id)->firstOrFail();
            return [
                [
                    'bank_name' => $driver->bank_name,
                    'account_number' => $driver->account_number,
                ],
                ['driver_id' => $driver->id],
            ];
        }

        if ($user->hasRole('partner')) {
            $partner = Partner::where('user_id', $user->id)->firstOrFail();
            return [
                [
                    'bank_name' => $partner->bank_name,
                    'account_number' => $partner->account_number,
                ],
                ['partner_id' => $partner->id],
            ];
        }

        if ($user->hasRole('investor')) {
            $investor = Investor::where('user_id', $user->id)->firstOrFail();
            return [
                [
                    'bank_name' => $investor->bank_name,
                    'account_number' => $investor->account_number,
                ],
                ['investor_id' => $investor->id],
            ];
        }

        // Default: user role
        return [
            [
                'bank_name' => $user->bank_name,
                'account_number' => $user->account_number,
            ],
            ['user_id' => $user->id],
        ];
    }


    protected function notifyUser($user, Payout $payout): void
    {
        $message = "Hi {$user->name}, your LoopFreight payout of {$payout->currency} {$payout->amount} has been initiated.";

        try {
            // In-app
            $user->notify(new PayoutInitiatedNotification($payout));

            // Email
            if (!empty($user->email)) {
                Mail::to($user->email)->send(new PayoutInitiatedMail($payout));
            }

            if (!empty($user->phone)) {
                $this->termii->sendSms($user->phone, $message);
            }

            if (!empty($user->whatsapp_number)) {
                $this->twilio->sendWhatsAppMessage($user->whatsapp_number, $message);
            }
        } catch (\Throwable $e) {
            logError('Payout notify failed', $e);
        }
    }
}
