<?php
namespace App\DTOs\Partner;

class PartnerDriversDTO
{
    public int $totalDrivers;
    public array $drivers;
    public array $pagination;

    public function __construct(int $totalDrivers, array $drivers, array $pagination = [])
    {
        $this->totalDrivers = $totalDrivers;
        $this->drivers      = $drivers;
        $this->pagination   = $pagination;
    }
}
