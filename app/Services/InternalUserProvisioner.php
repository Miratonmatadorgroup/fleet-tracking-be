<?php
namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use App\Services\ExternalBankService;

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

            //Create internal wallet
            Wallet::create([
                'user_id'            => $user->id,
                'account_number'     => $externalAccount['account_number'],
                'bank_name'          => $externalAccount['bank_name'] ?? 'Shanono Bank',
                'external_reference' => $externalAccount['account_number'],
                'balance'            => 0,
            ]);
        });
    }
}
