<?php
namespace App\DTOs\Partner;

use Illuminate\Http\Request;

class PartnerIndexDTO
{
    public function __construct(
        public int $perPage = 10,
        public int $page = 1
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            perPage: $request->get('per_page', 10),
            page: $request->get('page', 1)
        );
    }
}
