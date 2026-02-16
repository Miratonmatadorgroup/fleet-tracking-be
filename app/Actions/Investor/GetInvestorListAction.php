<?php

namespace App\Actions\Investor;

use App\Models\Investor;
use App\DTOs\Investor\InvestorListDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetInvestorListAction
{
    public function execute(InvestorListDTO $dto, ?string $search = null): LengthAwarePaginator
    {
        $query = Investor::with('user')
            ->when($dto->status, fn($q) => $q->where('application_status', $dto->status))
            ->orderBy('created_at', 'desc');

        if (!empty($search)) {
            $search = strtolower(trim($search));

            $query->where(function ($q) use ($search) {
                $driverName = $q->getConnection()->getDriverName();

                // PostgreSQL version
                if ($driverName === 'pgsql') {
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //supports state/local gov/country search
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(payment_method) LIKE ?', ["%{$search}%"]);
                }
                // MySQL and others
                else {
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //supports state/local gov/country search
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(payment_method) LIKE ?', ["%{$search}%"]);
                }
            });
        }

        return $query->paginate($dto->perPage);
    }
}
