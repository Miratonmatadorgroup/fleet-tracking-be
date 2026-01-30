<?php
namespace App\Events\Investor;

use App\Models\InvestmentPlan;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class InvestmentPlanCreated
{
    use Dispatchable, SerializesModels;

    public InvestmentPlan $plan;

    public function __construct(InvestmentPlan $plan)
    {
        $this->plan = $plan;
    }
}
