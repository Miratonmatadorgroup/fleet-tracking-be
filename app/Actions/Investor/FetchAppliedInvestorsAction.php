<?php

namespace App\Actions\Investor;

use App\Models\Investor;
use App\DTOs\Investor\AppliedInvestorsDTO;
use App\Enums\InvestorApplicationStatusEnums;
use Illuminate\Pagination\LengthAwarePaginator;

class FetchAppliedInvestorsAction
{
    public function execute(AppliedInvestorsDTO $dto, ?string $search = null): LengthAwarePaginator
    {
        $query = Investor::with('user')
            ->where('application_status', InvestorApplicationStatusEnums::REVIEW)
            ->orderByDesc('created_at');

        if (!empty($search)) {
            $search = strtolower(trim($search));

            $query->where(function ($q) use ($search) {
                $driverName = $q->getConnection()->getDriverName();

                if ($driverName === 'pgsql') {
                    //PostgreSQL: use ILIKE
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(gender) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(payment_method) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('CAST(investment_amount AS TEXT) LIKE ?', ["%{$search}%"]);
                } else {
                    // MySQL: use LOWER + LIKE
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(gender) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(payment_method) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('CAST(investment_amount AS CHAR) LIKE ?', ["%{$search}%"]);
                }

                //Optionally include related user model
                $q->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                });
            });
        }

        return $query->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}

