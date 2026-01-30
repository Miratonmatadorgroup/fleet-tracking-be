<?php
namespace App\Actions\Wallet;

use App\Models\WalletTransaction;
use App\DTOs\Wallet\UserTransactionsDTO;

class GetUserTransactionsAction
{
    public function execute(UserTransactionsDTO $dto, int $perPage = 10)
    {
        return WalletTransaction::with('wallet')
            ->where('user_id', $dto->user->id)
            ->latest()
            ->paginate($perPage);
    }
}
