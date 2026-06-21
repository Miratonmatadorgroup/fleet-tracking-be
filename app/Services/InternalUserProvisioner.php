<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\ExternalBankService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InternalUserProvisioner
{
    public function __construct(
        protected ExternalBankService $externalBankService
    ) {}

    public function provision(User $user): void
    {
        DB::transaction(function () use ($user) {

            //Create Shanono sub-account
            $externalAccount = $this->externalBankService
                ->createExternalAccountForUser($user);

            Log::info('External account response', $externalAccount);

            $accountNumber = data_get($externalAccount, 'number');

            if (!$accountNumber) {
                throw new \Exception(
                    'External bank account number was not returned.'
                );
            }
            
            //Create internal wallet
            Wallet::create([
                'user_id'            => $user->id,
                'account_number'     => $accountNumber,
                'bank_name'          => data_get(
                    $externalAccount,
                    'bank_name',
                    'Shanono Bank'
                ),
                'external_reference' => data_get($externalAccount, 'id'),
                'balance' => 0,
            ]);

            //Create internal wallet
            // Wallet::create([
            //     'user_id'            => $user->id,
            //     'account_number'     => $externalAccount['number'],
            //     'bank_name'          => $externalAccount['bank_name'] ?? 'Shanono Bank',
            //     'external_reference' => $externalAccount['account_number'],
            //     'balance'            => 0,
            // ]);
        });
    }
}
