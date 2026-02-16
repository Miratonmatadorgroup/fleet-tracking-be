<?php
namespace App\DTOs\Partner;


class PartnerTransportModesDTO
{
    public function __construct(
        public readonly int $totalTransportModes,
        public readonly array $transportModes,
        public readonly array $pagination = []
    ) {}
}
