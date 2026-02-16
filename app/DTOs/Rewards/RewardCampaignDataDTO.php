<?php
namespace App\DTOs\Rewards;

use Illuminate\Http\Request;

class RewardCampaignDataDTO
{
    public function __construct(
        public string $name,
        public string $type,
        public float $reward_amount,
        public ?string $starts_at,
        public ?string $ends_at,
        public array $meta
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'reward_amount' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'meta' => 'nullable|array',
        ]);

        return new self(
            $validated['name'],
            $validated['type'],
            $validated['reward_amount'],
            $validated['starts_at'] ?? null,
            $validated['ends_at'] ?? null,
            $validated['meta'] ?? []
        );
    }
}
