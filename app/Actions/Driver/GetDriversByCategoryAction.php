<?php

namespace App\Actions\Driver;

use App\Models\Driver;
use App\DTOs\Driver\GetDriversByCategoryDTO;

class GetDriversByCategoryAction
{
    public static function execute(GetDriversByCategoryDTO $dto): array
    {
        $platformDrivers = Driver::whereHas('transportModeDetails', function ($query) {
                $query->whereNull('partner_id');
            })
            ->with('transportModeDetails')
            ->paginate($dto->perPage);

        $partnerDrivers = Driver::whereHas('transportModeDetails', function ($query) {
                $query->whereNotNull('partner_id');
            })
            ->with('transportModeDetails')
            ->paginate($dto->perPage);

        return [
            'platform_drivers' => [
                'total' => $platformDrivers->total(),
                'list'  => $platformDrivers
            ],
            'partner_drivers' => [
                'total' => $partnerDrivers->total(),
                'list'  => $partnerDrivers
            ],
        ];
    }
}
