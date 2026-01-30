<?php

namespace App\DTOs\ApiClient;

use Illuminate\Http\Request;

class BlockApiClientDTO
{
    public function __construct(
        public readonly string $apiClientId,
        public readonly bool $block
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            apiClientId: $request->input('api_client_id'),
            block: filter_var($request->input('block'), FILTER_VALIDATE_BOOLEAN) 
        );
    }
}
