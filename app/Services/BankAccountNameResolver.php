<?php

namespace App\Services;

use App\Services\ExternalBankService;
use Exception;

class BankAccountNameResolver
{
    public function resolve(string $accountNumber, string $bankCode): array
    {
        try {
            $shanono = app(ExternalBankService::class);

            $data = $shanono->lifeBankNameEnquiry($accountNumber, $bankCode);

            return [
                'success'      => true,
                'account_name' => $data['accountName'] ?? null,
                'raw'          => $data,
                'provider'     => 'shanono',
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'error'   => $e->getMessage() ?: 'Unable to verify bank account at this time',
                'provider' => 'shanono',
            ];
        }
    }
}
