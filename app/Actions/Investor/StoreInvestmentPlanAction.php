<?php
namespace App\Actions\Investor;

use App\Models\InvestmentPlan;
use App\DTOs\Investor\InvestmentPlanDTO;
use App\Events\Investor\InvestmentPlanCreated;

class StoreInvestmentPlanAction
{
    public function execute(InvestmentPlanDTO $dto): InvestmentPlan
    {
        $plan = InvestmentPlan::create([
            'name'   => $dto->name,
            'amount' => $dto->amount,
            'label'  => $dto->label ?? "₦" . number_format($dto->amount, 2),
        ]);

        event(new InvestmentPlanCreated($plan));

        return $plan;
    }
}
