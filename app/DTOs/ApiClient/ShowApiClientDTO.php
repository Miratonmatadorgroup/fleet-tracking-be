<?php

namespace App\DTOs\ApiClient;

use Illuminate\Http\Request;

class ShowApiClientDTO
{
    public function __construct(
        public int $perPage = 10,
        public ?string $search = null  
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            perPage: (int) $request->query('per_page', 10),
            search: $request->query('search')  
        );
    }
}
