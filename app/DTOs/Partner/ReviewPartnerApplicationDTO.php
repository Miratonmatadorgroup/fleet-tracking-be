<?php

namespace App\DTOs\Partner;

use App\Enums\PartnerApplicationStatusEnums;
use Illuminate\Http\Request;


class ReviewPartnerApplicationDTO
{
    public function __construct(
        public string $partnerId,
        public PartnerApplicationStatusEnums $applicationStatus
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'action'     => 'required|in:approve,reject',
        ]);

        $applicationStatus = match ($validated['action']) {
            'approve' => PartnerApplicationStatusEnums::APPROVED,
            'reject'  => PartnerApplicationStatusEnums::REJECTED,
        };

        return new self(
            partnerId: $validated['partner_id'],
            applicationStatus: $applicationStatus
        );
    }
}
