<?php
namespace App\DTOs\Rewards;


class ClaimRewardDTO
{
    public function __construct(
        public string $driver_id,
        public string $claim_id
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            driver_id: $request->user()->id,
            claim_id: $request->input('claim_id')
        );
    }
}
