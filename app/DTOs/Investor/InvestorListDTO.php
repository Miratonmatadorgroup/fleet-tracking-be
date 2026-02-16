<?php

namespace App\DTOs\Investor;

class InvestorListDTO
{
    public ?string $status;
    public int $perPage;

    public function __construct(array $data)
    {
        $this->status = $data['status'] ?? null;
        $this->perPage = isset($data['per_page']) && is_numeric($data['per_page'])
            ? (int) $data['per_page']
            : 10; // default
    }
}
