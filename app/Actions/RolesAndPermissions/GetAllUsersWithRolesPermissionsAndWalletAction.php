<?php

namespace App\Actions\RolesAndPermissions;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\ExternalBankService;

class GetAllUsersWithRolesPermissionsAndWalletAction
{

    public function __construct(
        protected ExternalBankService $externalBankService
    ) {}
    public function execute(?string $search = null, int $perPage = 10)
    {
        $query = User::with('wallet')
            ->orderBy('created_at', 'desc');

        if (!empty($search)) {
            $search = strtolower($search);

            $query->where(function ($q) use ($search) {
                // Cast `id` to text explicitly for PostgreSQL
                $driver = $q->getConnection()->getDriverName();

                if ($driver === 'pgsql') {
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"]);
                } else {
                    // MySQL and others
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"]);
                }
            });
        }

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $wallet = $user->wallet;

            $walletData = null;

            if ($wallet) {
                // Defaults
                $externalAvailable = "0.00";
                $externalBook      = "0.00";

                try {
                    if ($wallet->external_account_number) {
                        $balances = $this->externalBankService
                            ->getAccountBalanceCached($wallet->external_account_number);

                        $externalAvailable = number_format(
                            $balances['available_balance'],
                            2,
                            '.',
                            ''
                        );

                        $externalBook = number_format(
                            $balances['book_balance'],
                            2,
                            '.',
                            ''
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to fetch Shanono balance (admin list)', [
                        'wallet_id' => $wallet->id,
                        'error'     => $e->getMessage(),
                    ]);
                }

                $walletData = [
                    // INTERNAL WALLET
                    'account_number'     => $wallet->account_number,
                    'available_balance'  => $wallet->available_balance,
                    'total_balance'      => $wallet->total_balance,
                    'pending_balance'    => $wallet->pending_balance ?? "0.00",
                    'currency'           => $wallet->currency,
                    'is_virtual_account' => (bool) $wallet->is_virtual_account,
                    'provider'           => $wallet->provider,

                    // EXTERNAL BANK DETAILS
                    'external_account_number' => $wallet->external_account_number,
                    'external_account_name'   => $wallet->external_account_name,
                    'external_bank'           => $wallet->external_bank,
                    'external_reference'      => $wallet->external_reference,

                    // REAL-TIME BALANCES
                    'external_available_balance' => $externalAvailable,
                    'external_book_balance'      => $externalBook,
                ];
            }

            return [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'phone'            => $user->phone,
                'payout_restricted' => $user->payout_restricted,
                'whatsapp_number'  => $user->whatsapp_number,
                'roles'            => $user->getRoleNames(),
                'permissions'      => $user->getAllPermissions()->pluck('name'),
                'wallet'           => $walletData,
            ];
        });


        return $users;
    }
}
