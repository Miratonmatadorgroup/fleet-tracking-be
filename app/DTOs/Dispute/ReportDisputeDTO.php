<?php
namespace App\DTOs\Dispute;

use Illuminate\Support\Facades\Auth;

class ReportDisputeDTO
{
    public function __construct(
        public readonly string $user_id,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $tracking_number = null,
        public readonly ?string $driver_contact = null,
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            user_id: (string) Auth::id(),
            title: $request->input('title'),
            description: $request->input('description'),
            tracking_number: $request->input('tracking_number'),
            driver_contact: $request->input('driver_contact'),
        );
    }
}
