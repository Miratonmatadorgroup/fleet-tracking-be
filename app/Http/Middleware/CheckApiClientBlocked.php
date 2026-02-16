<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiClient;

class CheckApiClientBlocked
{
    public function handle(Request $request, Closure $next)
    {
        /** @var ApiClient $apiClient */
        $apiClient = $request->attributes->get('api_client');

        if ($apiClient && $apiClient->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Your access has been blocked. Contact LoopFreight admin.',
            ], 403);
        }

        return $next($request);
    }
}
