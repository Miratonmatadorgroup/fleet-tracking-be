<?php
namespace App\Actions\TransportMode;

use App\Models\TransportMode;
use App\Enums\TransportModeCategoryEnums;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;


class ViewRidesAction
{
    public function execute(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = TransportMode::with(['driver'])
            ->where('category', TransportModeCategoryEnums::PASSENGER->value)
            ->latest();

        if (!empty($search)) {
            $search = strtolower($search);

            $query->where(function ($q) use ($search) {
                $driverName = $q->getConnection()->getDriverName();

                if ($driverName === 'pgsql') {
                    // PostgreSQL case-insensitive search
                    $q->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(manufacturer) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(model) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(registration_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(color) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('driver', function ($d) use ($search) {
                            $d->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                              ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                        });
                } else {
                    // MySQL and others
                    $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(type) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(manufacturer) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(model) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(registration_number) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(color) LIKE ?', ["%{$search}%"])
                        ->orWhereHas('driver', function ($d) use ($search) {
                            $d->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                              ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
                        });
                }
            });
        }

        return $query->paginate($perPage);
    }
}
