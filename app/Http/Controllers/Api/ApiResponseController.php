<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiResponseController extends Controller
{
    /**
     * Create a response example for an API endpoint
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_endpoint_id' => 'required|uuid|exists:api_endpoints,id',
            'status_code'     => 'required|integer',
            'description'     => 'nullable|string',
            'body'            => 'nullable|array',
        ]);

        $response = ApiResponse::updateOrCreate(
            [
                'api_endpoint_id' => $validated['api_endpoint_id'],
                'status_code'     => $validated['status_code'],
            ],
            [
                'description' => $validated['description'] ?? null,
                'body'        => $validated['body'] ?? null,
            ]
        );

        return successResponse(
            'Response example saved',
            $response
        );
    }

    /**
     * Delete a response example (UUID)
     */
    public function destroy(ApiResponse $apiResponse)
    {
        $apiResponse->delete();

        return successResponse('Response deleted');
    }
}
