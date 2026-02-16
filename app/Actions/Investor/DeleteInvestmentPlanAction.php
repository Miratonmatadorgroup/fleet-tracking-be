<?php
namespace App\Actions\Investor;

use App\Models\InvestmentPlan;
use App\Events\Investor\InvestmentPlanDeleted;

class DeleteInvestmentPlanAction
{
    public function execute(string $id): void
    {
        $plan = InvestmentPlan::findOrFail($id);
        $plan->delete();

        event(new InvestmentPlanDeleted($plan));
    }
}
