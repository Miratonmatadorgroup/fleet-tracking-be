<?php
namespace App\Events\Partner;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;


class PartnerEarningsViewed
{
    use Dispatchable;

    public function __construct(public User $partner) {}
}
