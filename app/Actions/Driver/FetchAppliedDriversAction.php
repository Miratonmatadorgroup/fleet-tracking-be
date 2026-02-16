<?php

namespace App\Actions\Driver;


use App\Models\Driver;
use App\DTOs\Driver\AppliedDriversDTO;
use App\Enums\DriverApplicationStatusEnums;
use Illuminate\Pagination\LengthAwarePaginator;

class FetchAppliedDriversAction
{
    
    public function execute(AppliedDriversDTO $dto, ?string $search = null): LengthAwarePaginator
    {
        $query = Driver::with('user', 'transportModeDetails')
            ->where('application_status', DriverApplicationStatusEnums::REVIEW)
            ->orderByDesc('created_at');

        if (!empty($search)) {
            $search = strtolower(trim($search));

            $query->where(function ($q) use ($search) {
                $driverName = $q->getConnection()->getDriverName();

                // PostgreSQL
                if ($driverName === 'pgsql') {
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //Location-based search
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(transport_mode) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(driver_license_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"]);
                }
                // MySQL and others
                else {
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //Location-based search
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(transport_mode) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(driver_license_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"]);
                }
            });
        }

        return $query->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
