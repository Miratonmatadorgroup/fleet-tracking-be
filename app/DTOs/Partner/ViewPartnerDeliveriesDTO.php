<?php

namespace App\DTOs\Partner;

use Illuminate\Support\Facades\Auth;
use App\Models\Partner;

class ViewPartnerDeliveriesDTO
{
    public Partner $partner;
    public int $perPage;


    public ?string $search;

    public static function fromAuth(): self
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('partner')) {
            abort(403, "Unauthorized. You must be logged in as a partner.");
        }

        $partner = $user->partner;

        if (!$partner) {
            abort(404, "Partner profile not found for this user.");
        }

        $perPage = request()->query('per_page', 10);
        $search = request()->query('search');

        return new self($partner, (int) $perPage, $search);
    }

    public function __construct(Partner $partner, int $perPage = 10, ?string $search = null)
    {
        $this->partner = $partner;
        $this->perPage = $perPage;
        $this->search = $search;
    }
}
