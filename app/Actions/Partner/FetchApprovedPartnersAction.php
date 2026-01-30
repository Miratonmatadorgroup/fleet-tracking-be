<?php

namespace App\Actions\Partner;

use App\Models\Partner;
use App\DTOs\Partner\ApprovedPartnersDTO;
use App\Enums\PartnerApplicationStatusEnums;

class FetchApprovedPartnersAction
{
    public function execute(ApprovedPartnersDTO $dto)
    {
        return Partner::with('user', 'transportModes')
            ->where('application_status', PartnerApplicationStatusEnums::APPROVED)
            ->orderByDesc('created_at')
            ->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
