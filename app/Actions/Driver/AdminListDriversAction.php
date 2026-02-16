<?php

namespace App\Actions\Driver;

use App\Models\Driver;
use Illuminate\Support\Facades\Storage;

class AdminListDriversAction
{
    public function execute(?string $search = null, int $perPage = 10)
    {
        $query = Driver::orderBy('created_at', 'desc');

        if (!empty($search)) {
            $search = strtolower(trim($search));

            $query->where(function ($q) use ($search) {
                $driverName = $q->getConnection()->getDriverName();

                // --- PostgreSQL version ---
                if ($driverName === 'pgsql') {
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //supports locality/state search
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(transport_mode) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(driver_license_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"]);
                }

                // --- MySQL and others ---
                else {
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(whatsapp_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(address) LIKE ?', ["%{$search}%"]) //address-based filtering
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(application_status) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(transport_mode) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(driver_license_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(bank_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(next_of_kin_phone) LIKE ?', ["%{$search}%"]);
                }
            });
        }

        return $query->paginate($perPage);
    }
}
