<?php
namespace App\Events\Partner;

use App\Models\Partner;


class PartnerApplicationReviewedEvent
{
    public function __construct(
        public Partner $partner,
        public bool $approved
    ) {}
}
