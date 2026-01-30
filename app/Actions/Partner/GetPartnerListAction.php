<?php

namespace App\Actions\Partner;

use App\Models\Partner;
use App\DTOs\Partner\PartnerIndexDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPartnerListAction
{

    public function execute(PartnerIndexDTO $dto, ?string $search = null): LengthAwarePaginator
    {
        $query = Partner::orderBy('created_at', 'desc');

        if (!empty($search)) {
            $search = strtolower(trim($search));

            $query->where(function ($q) use ($search) {
                $driverName = $q->getConnection()->getDriverName();

                // PostgreSQL
                if ($driverName === 'pgsql') {
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //supports local gov/state/country
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"]);
                }
                // MySQL / MariaDB
                else {
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //address-based search
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"]);
                }
            });
        }

        return $query->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
