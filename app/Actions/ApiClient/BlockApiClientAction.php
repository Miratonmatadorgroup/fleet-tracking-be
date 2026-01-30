<?php

namespace App\Actions\ApiClient;

use App\Models\ApiClient;
use App\DTOs\ApiClient\BlockApiClientDTO;
use App\Events\ApiClient\ApiClientBlocked;

class BlockApiClientAction
{
    public function execute(BlockApiClientDTO $dto): ApiClient
    {
        $client = ApiClient::findOrFail($dto->apiClientId);

        $client->update([
            'is_blocked' => $dto->block,
        ]);

        event(new ApiClientBlocked($client, $dto->block));

        return $client->fresh();
    }

}
