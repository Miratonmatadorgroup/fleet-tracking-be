<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\DTOs\ApiClient\ShowApiClientDTO;
use App\Events\ApiClient\ApiClientViewed;
use App\Actions\ApiClient\ShowApiClientAction;

class ApiClientController extends Controller
{
    public function show(Request $request)
    {
        try {
            $dto = ShowApiClientDTO::fromRequest($request);

            $clients = app(ShowApiClientAction::class)->execute($dto);

            event(new ApiClientViewed($clients));

            return successResponse('API clients retrieved successfully.', $clients->through(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'api_key' => $client->api_key,
                    'environment' => $client->environment,
                    'active' => $client->active,
                    'ip_whitelist' => $client->ip_whitelist,
                    'created_at' => $client->created_at,
                    'updated_at' => $client->updated_at,
                    'is_blocked' => $client->is_blocked,
                    'CUSTOMER_ID' => $client->customer?->id,
                ];
            }));
        } catch (\Throwable $th) {
            return failureResponse('Failed to retrieve API clients.', 500, 'server_error', $th);
        }
    }
}
