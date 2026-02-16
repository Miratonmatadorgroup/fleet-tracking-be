<?php

namespace App\DTOs\Driver;

use Illuminate\Http\Request;

class GetDriversByCategoryDTO
{
    public int $perPage;

    public function __construct(int $perPage = 10)
    {
        $this->perPage = $perPage;
    }

    public static function fromRequest(Request $request): self
    {
        $perPage = (int) $request->get('per_page', 10);
        return new self($perPage);
    }
}
