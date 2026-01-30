<?php
namespace App\DTOs\Dispute;

use Illuminate\Http\Request;

class UpdateDisputeStatusDTO
{
    public function __construct(
        public readonly string $dispute_id,
        public readonly string $action
    ) {}

    public static function fromRequest(Request $request, string $dispute_id): self
    {
        return new self(
            dispute_id: $dispute_id,
            action: $request->action
        );
    }
}
