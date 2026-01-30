<?php

namespace App\Actions\Driver;

use App\Models\Driver;
use App\DTOs\Driver\ApprovedDriversDTO;
use App\Enums\DriverApplicationStatusEnums;

class FetchApprovedDriversAction
{
    public function execute(ApprovedDriversDTO $dto)
    {
        return Driver::with('user', 'transportModeDetails')
            ->where('application_status', DriverApplicationStatusEnums::APPROVED)
            ->orderByDesc('created_at')
            ->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
