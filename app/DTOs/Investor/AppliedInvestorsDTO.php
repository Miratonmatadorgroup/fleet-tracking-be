<?php
namespace App\DTOs\Investor;

use Illuminate\Http\Request;

class AppliedInvestorsDTO
{
    public int $page;
    public int $perPage;
    public ?string $search;

    public static function fromRequest(Request $request): self
    {
        return new self(
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 10),
            search: $request->query('search')
        );
    }

    public function __construct(int $page, int $perPage, ?string $search = null)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->search = $search;
    }
}

