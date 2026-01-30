<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiAuth;
use Illuminate\Http\Request;
use App\Enums\ApiAuthTypesEnums;
use App\Http\Controllers\Controller;

class ApiAuthController extends Controller
{
    /**
     * Create or update auth configuration for an API endpoint
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_endpoint_id' => 'required|uuid|exists:api_endpoints,id',
            'type'            => 'required|in:none,bearer_token,api_key,basic',
            'description'     => 'nullable|string',
        ]);

        $auth = ApiAuth::updateOrCreate(
            [
                'api_endpoint_id' => $validated['api_endpoint_id'],
            ],
            [
                'type'        => ApiAuthTypesEnums::from($validated['type']),
                'description' => $validated['description'] ?? null,
            ]
        );

        return successResponse('Auth configuration saved', $auth);
    }
}
