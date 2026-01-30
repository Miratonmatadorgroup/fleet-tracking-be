<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API Key missing'], 401);
        }

        $client = ApiClient::where('api_key', $apiKey)->where('active', true)->first();

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Invalid API Key'], 403);
        }

        if (is_array($client->ip_whitelist) && count($client->ip_whitelist)) {
            $ip = $request->ip();
            if (!in_array($ip, $client->ip_whitelist, true)) {
                return response()->json(['success' => false, 'message' => 'IP not allowed'], 403);
            }
        }
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
