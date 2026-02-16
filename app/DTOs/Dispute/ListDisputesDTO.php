<?php

namespace App\DTOs\Dispute;

use Illuminate\Http\Request;


class ListDisputesDTO
{
    public function __construct(
        public readonly ?string $status,
        public readonly int $perPage,
        public readonly int $page,
        public readonly ?string $search  
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: $request->get('status'),
            perPage: $request->get('per_page', 10),
            page: $request->get('page', 1),
            search: $request->get('search')  
        );
    }
}
