<?php
namespace App\DTOs\Investor;

use Illuminate\Http\Request;


class ApprovedInvestorsDTO
{
    public int $page;
    public int $perPage;

    public static function fromRequest(Request $request): self
    {
        return new self(
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 10)
        );
    }

    public function __construct(int $page, int $perPage)
    {
        $this->page = $page;
        $this->perPage = $perPage;
    }
}
