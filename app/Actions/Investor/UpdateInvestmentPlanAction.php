<?php
namespace App\Actions\Investor;

use App\Models\InvestmentPlan;
use App\DTOs\Investor\UpdateInvestmentPlanDTO;
use App\Events\Investor\InvestmentPlanUpdated;

class UpdateInvestmentPlanAction
{
    public function execute(UpdateInvestmentPlanDTO $dto, string $id): InvestmentPlan
    {
        $plan = InvestmentPlan::findOrFail($id);

        $plan->update([
            'name'   => $dto->name ?? $plan->name,
            'amount' => $dto->amount ?? $plan->amount,
            'label'  => $dto->label ?? $plan->label,
        ]);

        event(new InvestmentPlanUpdated($plan));

        return $plan;
    }
}
