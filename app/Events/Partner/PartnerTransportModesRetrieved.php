<?php
namespace App\Events\Partner;

use App\Models\Partner;

class PartnerTransportModesRetrieved
{
    public function __construct(
        public readonly Partner $partner,
        public readonly array $transportModes
    ) {}
}
