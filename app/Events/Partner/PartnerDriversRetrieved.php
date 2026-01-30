<?php
namespace App\Events\Partner;

use App\Models\Partner;


class PartnerDriversRetrieved
{
    public function __construct(
        public readonly Partner $partner,
        public readonly array $drivers
    ) {}
}
