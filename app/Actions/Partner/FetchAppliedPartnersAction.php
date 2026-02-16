<?php

namespace App\Actions\Partner;

use App\Models\Partner;
use App\DTOs\Partner\AppliedPartnersDTO;
use App\Enums\PartnerApplicationStatusEnums;
use Illuminate\Pagination\LengthAwarePaginator;

class FetchAppliedPartnersAction
{
    public function execute(AppliedPartnersDTO $dto, ?string $search = null): LengthAwarePaginator
    {
        $query = Partner::with('user', 'transportModes')
            ->where('application_status', PartnerApplicationStatusEnums::REVIEW)
            ->orderByDesc('created_at');

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
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //Location-based search
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"]);
                } 
                // MySQL and others
                else {
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(business_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //Location-based search
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"]);
                }
            });
        }

        return $query->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
