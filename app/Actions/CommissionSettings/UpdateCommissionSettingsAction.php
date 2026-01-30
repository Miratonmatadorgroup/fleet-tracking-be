<?php

namespace App\Actions\CommissionSettings;

use App\Models\Commission;
use App\DTOs\CommissionSettings\UpdateCommissionSettingsDTO;

class UpdateCommissionSettingsAction
{
    public static function execute(UpdateCommissionSettingsDTO $dto): array
    {
        $total = collect($dto->commissions)->sum('percentage');

        if (abs($total - 100.0) > 0.001) {
            throw new \Exception("Total commission must equal exactly 100%. Current total: {$total}%", 422);
        }

        foreach ($dto->commissions as $entry) {
            Commission::updateOrCreate(
                ['role' => $entry['role']],
                ['percentage' => $entry['percentage']]
            );
        }

        return Commission::all()->toArray();
    }
}
