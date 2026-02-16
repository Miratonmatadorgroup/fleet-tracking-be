<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiHeader;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class ApiHeaderController extends Controller
{
    /**
     * Create a new API header
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_endpoint_id' => 'required|uuid|exists:api_endpoints,id',
            'name'            => 'required|string',
            'value'           => 'nullable|string',
            'is_required'     => 'sometimes|boolean',
            'description'    => 'nullable|string',
        ]);

        $header = ApiHeader::updateOrCreate(
            [
                'api_endpoint_id' => $validated['api_endpoint_id'],
                'name'            => $validated['name'],
            ],
            [
                'value'        => $validated['value'] ?? null,
                'is_required'  => $validated['is_required'] ?? false,
                'description' => $validated['description'] ?? null,
            ]
        );

        return successResponse(
            'Header saved',
            $header
        );
    }

    /**
     * Delete an API header (UUID)
     */
    public function destroy(ApiHeader $apiHeader)
    {
        $apiHeader->delete();

        return successResponse('Header deleted');
    }
}
