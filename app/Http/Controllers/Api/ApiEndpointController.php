<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiEndpoint;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ApiEndpointService;
use App\Enums\ApiEndpointsMethodEnums;

class ApiEndpointController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiEndpoint::with([
            'project',
            'requests',
            'headers',
            'responses',
            'auth',
        ]);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('method')) {
            $query->where(
                'method',
                ApiEndpointsMethodEnums::from(
                    strtolower($request->input('method'))
                )->value
            );
        }


        if ($request->filled('version')) {
            $query->where('version', $request->version);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return successResponse(
            'Endpoints fetched',
            $query->latest()->get()
        );
    }

    public function store(Request $request, ApiEndpointService $service)
    {
        $validated = $request->validate([
            'project_id'  => 'required|uuid|exists:projects,id',
            'title'       => 'required|string',
            'method'      => 'required|string|in:get,post,put,delete',
            'path'        => 'required|string',
            'full_url'    => 'required|string',
            'description' => 'nullable|string',
            'version'     => 'nullable|string',
        ]);

        $endpoint = $service->create($validated);

        return successResponse('Endpoint created', $endpoint);
    }

    public function update(Request $request, ApiEndpoint $endpoint, ApiEndpointService $service)
    {
        $validated = $request->validate([
            'title'       => 'sometimes|string',
            'method'      => 'sometimes|string|in:get,post,put,delete',
            'path'        => 'sometimes|string',
            'description' => 'nullable|string',
            'version'     => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $endpoint = $service->update($endpoint, $validated);

        return successResponse('Endpoint updated', $endpoint);
    }

    public function destroy(ApiEndpoint $endpoint, ApiEndpointService $service)
    {
        $service->delete($endpoint);

        return successResponse('Endpoint deleted');
    }

    public function show(ApiEndpoint $endpoint)
    {
        // Load all related data
        $endpoint->load([
            'project',
            'requests',
            'headers',
            'responses',
            'auth',
        ]);

        return successResponse('Endpoint details', $endpoint);
    }
}
