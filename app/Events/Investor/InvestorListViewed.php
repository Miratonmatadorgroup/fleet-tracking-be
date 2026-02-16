<?php
namespace App\Events\Investor;

use Illuminate\Queue\SerializesModels;

class InvestorListViewed
{
    use SerializesModels;

    public function __construct(public array $filters)
    {
    }
}
