<?php
namespace App\Events\Partner;

use Illuminate\Pagination\LengthAwarePaginator;

class PartnerListFetchedEvent
{
    public function __construct(
        public LengthAwarePaginator $partners,
        public int $total
    ) {}
}
