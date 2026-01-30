<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiRequest;
use Illuminate\Http\Request;
use App\Enums\ApiRequestsTypesEnums;
use App\Http\Controllers\Controller;

class ApiRequestController extends Controller
{
    /**
     * Store a new API request schema
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_endpoint_id' => 'required|uuid|exists:api_endpoints,id',
            'type'            => 'required|in:body,query,path',
            'content_type'    => 'nullable|string',
            'schema'          => 'nullable|array',
        ]);

        $apiRequest = ApiRequest::updateOrCreate(
            [
                'api_endpoint_id' => $validated['api_endpoint_id'],
                'type'            => ApiRequestsTypesEnums::from($validated['type']),
            ],
            [
                'content_type' => $validated['content_type'] ?? null,
                'schema'       => $validated['schema'] ?? null,
            ]
        );

        return successResponse(
            'Request schema saved',
            $apiRequest
        );
    }

    /**
     * Delete an API request schema by UUID
     */
    public function destroy(ApiRequest $apiRequest)
    {
        $apiRequest->delete();

        return successResponse('Request schema deleted');
    }
}
