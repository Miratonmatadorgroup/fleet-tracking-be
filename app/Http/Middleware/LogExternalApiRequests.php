<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Services\ExternalApiLoggerService;
use Closure;
use Illuminate\Http\Request;

class LogExternalApiRequests
{
    protected ExternalApiLoggerService $logger;

    public function __construct(ExternalApiLoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $apiClient = $request->attributes->get('api_client');

        if ($apiClient instanceof ApiClient) {
            $this->logger->log(
                $apiClient,
                $request,
                $response->isSuccessful(),
                $response->getStatusCode()
            );
        }

        return $response;
    }
}
