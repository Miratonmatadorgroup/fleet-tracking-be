<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiClientWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExternalWebhookController extends Controller
{
    public function store(Request $request, ApiClient $apiClient)
    {
        $request->validate([
            'webhook_url' => ['required', 'url'],
        ]);

        $webhook = ApiClientWebhook::firstOrNew([
            'api_client_id' => $apiClient->id,
        ]);

        $webhook->webhook_url = $request->webhook_url;
        $webhook->is_active = true;

        // Only create secret if webhook is new
        if (!$webhook->exists) {
            $webhook->webhook_secret = Str::random(64);
        }

        $webhook->save();
        return successResponse(
            'Webhook created successfully',
            $webhook
        );
    }

    /**
     * Show authenticated user's webhook
     */
    public function show(Request $request)
    {
        $apiClient = $request->attributes->get('api_client');

        $webhook = ApiClientWebhook::where(
            'api_client_id',
            $apiClient->id
        )->first();

        if (!$webhook) {
            return failureResponse(
                'Webhook not configured',
                404
            );
        }

        return successResponse(
            'Webhook fetched successfully',
            $webhook
        );
    }

    /**
     * List all webhooks
     * Requires permission: view-list-webhooks
     */

    public function listWebhooks(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->can('view-list-webhooks')) {
            return failureResponse(
                'Unauthorized',
                403
            );
        }

        $query = ApiClientWebhook::with(['apiClient']);

        // Global Search
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('webhook_url', 'like', "%{$search}%")
                    ->orWhereHas('apiClient', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by API Client ID
        if ($request->filled('api_client_id')) {
            $query->where('api_client_id', $request->api_client_id);
        }

        // Filter by date range
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        $webhooks = $query
            ->latest()
            ->paginate($request->get('per_page', 20));

        return successResponse(
            'Webhook list fetched successfully',
            $webhooks
        );
    }

    public function update(Request $request, string $apiClient)
    {
        $user = $request->user();

        // Extra safety
        if (!$user || !$user->can('modify-webhooks')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Find API client manually using UUID
        $apiClient = ApiClient::find($apiClient);

        if (!$apiClient) {
            return response()->json([
                'message' => 'API Client not found',
            ], 404);
        }

        $validated = $request->validate([
            'webhook_url' => ['required', 'url'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $webhook = ApiClientWebhook::where(
            'api_client_id',
            $apiClient->id
        )->first();

        if (!$webhook) {
            return response()->json([
                'message' => 'Webhook not found',
            ], 404);
        }

        $webhook->update([
            'webhook_url' => $validated['webhook_url'],
            'is_active' => $request->has('is_active')
                ? $validated['is_active']
                : $webhook->is_active,
        ]);

        return successResponse(
            'Webhook updated successfully',
            $webhook->fresh()
        );
    }
}
