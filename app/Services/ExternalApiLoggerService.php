<?php
namespace App\Services;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use Illuminate\Http\Request;

class ExternalApiLoggerService
{
    public function log(
        ApiClient $apiClient,
        Request $request,
        bool $isSuccessful,
        int $responseCode
    ): void {
        ApiRequestLog::create([
            'api_client_id' => $apiClient->id,
            'endpoint'      => $request->path(),
            'is_successful' => $isSuccessful,
            'response_code' => $responseCode,
        ]);
    }
}
