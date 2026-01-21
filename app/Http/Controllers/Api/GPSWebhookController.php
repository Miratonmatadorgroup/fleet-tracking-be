<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGpsData;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GpsWebhookController extends Controller
{
    public function receive(Request $request)
    {
        // Validate API Key
        if ($request->header('X-API-Key') !== config('services.gps.api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validate Payload
        $validated = $request->validate([
            'device_id' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'required|numeric|min:0',
            'ignition' => 'required|boolean',
            'heading' => 'nullable|numeric|between:0,360',
            'altitude' => 'nullable|numeric',
            'satellites' => 'nullable|integer|min:0',
            'hdop' => 'nullable|numeric|min:0',
            'timestamp' => 'required|date',
        ]);

        // Find Asset by Equipment ID
        $asset = Asset::where('equipment_id', $validated['device_id'])->first();

        if (!$asset) {
            Log::warning('GPS data received for unknown device', ['device_id' => $validated['device_id']]);
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Dispatch Job to Queue (async processing)
        ProcessGpsData::dispatch($asset, $validated);

        return response()->json(['status' => 'accepted'], 202);
    }
}