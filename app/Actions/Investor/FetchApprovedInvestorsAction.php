<?php

namespace App\Actions\Investor;

use App\Models\Investor;
use App\DTOs\Investor\ApprovedInvestorsDTO;
use App\Enums\InvestorApplicationStatusEnums;

class FetchApprovedInvestorsAction
{
    public function execute(ApprovedInvestorsDTO $dto)
    {
        return Investor::with('user')
            ->where('application_status', InvestorApplicationStatusEnums::APPROVED)
            ->orderByDesc('created_at')
            ->paginate($dto->perPage, ['*'], 'page', $dto->page);
    }
}
