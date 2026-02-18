<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = Asset::with(['driver', 'organization', 'activeSubscription']);

        // Filter by organization for non-super-admins
        if (!$request->user()->isSuperAdmin()) {
            $query->where('organization_id', $request->user()->organization_id);
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('class')) {
            $query->where('class', $request->class);
        }

        if ($request->has('organization_id') && $request->user()->isSuperAdmin()) {
            $query->where('organization_id', $request->organization_id);
        }

        $assets = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'equipment_id' => 'required|string|unique:assets',
            'asset_type' => 'required|in:car,bike,suv,truck,van,boat,helicopter,plane,ship',
            'class' => 'required|in:A,B,C',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'license_plate' => 'nullable|string|max:50',
            'vin' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'base_consumption_rate' => 'nullable|numeric|min:0',
            'idle_consumption_rate' => 'nullable|numeric|min:0',
            'speeding_penalty' => 'nullable|numeric|min:0|max:1',
            'driver_id' => 'nullable|exists:users,id',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['status'] = 'offline';

        $asset = Asset::create($validated);

        // Log audit
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'created',
            'entity_type' => 'Asset',
            'entity_id' => $asset->id,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($asset->load(['driver', 'organization']), 201);
    }

    public function show(Request $request, Asset $asset)
    {
        Gate::authorize('view', $asset);

        return response()->json($asset->load([
            'driver',
            'organization',
            'activeSubscription',
        ]));
    }

    public function update(Request $request, Asset $asset)
    {
        Gate::authorize('update', $asset);

        $validated = $request->validate([
            'asset_type' => 'sometimes|in:car,bike,suv,truck,van,boat,helicopter,plane,ship',
            'class' => 'sometimes|in:A,B,C',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'license_plate' => 'nullable|string|max:50',
            'vin' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'base_consumption_rate' => 'nullable|numeric|min:0',
            'idle_consumption_rate' => 'nullable|numeric|min:0',
            'speeding_penalty' => 'nullable|numeric|min:0|max:1',
            'driver_id' => 'nullable|exists:users,id',
        ]);

        $oldValues = $asset->toArray();
        $asset->update($validated);

        // Log audit
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'updated',
            'entity_type' => 'Asset',
            'entity_id' => $asset->id,
            'old_values' => $oldValues,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($asset->load(['driver', 'organization']));
    }

    public function destroy(Request $request, Asset $asset)
    {
        Gate::authorize('delete', $asset);

        $oldValues = $asset->toArray();
        $asset->delete();

        // Log audit
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'deleted',
            'entity_type' => 'Asset',
            'entity_id' => $asset->id,
            'old_values' => $oldValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Asset deleted successfully']);
    }

    public function location(Request $request, Asset $asset)
    {
        Gate::authorize('view', $asset);

        // Check subscription status
        if (!$asset->hasActiveSubscription() && !$request->user()->isSuperAdmin()) {
            return response()->json([
                'status' => 'subscription_expired',
                'message' => 'Your subscription has expired. Renew to restore full access.',
                'data' => [
                    'asset_id' => $asset->id,
                    'status' => $asset->status,
                    'last_ping_at' => $asset->last_ping_at,
                ],
            ], 402);
        }

        $location = $asset->getLastKnownLocation();

        if (!$location) {
            return response()->json([
                'message' => 'No location data available',
            ], 404);
        }

        return response()->json($location);
    }

    public function route(Request $request, Asset $asset)
    {
        Gate::authorize('view', $asset);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'interval' => 'nullable|integer|min:1|max:60',
        ]);

        // Check subscription status
        if (!$asset->hasActiveSubscription() && !$request->user()->isSuperAdmin()) {
            return response()->json([
                'status' => 'subscription_expired',
                'message' => 'Your subscription has expired. Renew to restore full access.',
            ], 402);
        }

        $interval = $validated['interval'] ?? 5; // Default 5 minutes

        $gpsLogs = $asset->gpsLogs()
            ->whereBetween('timestamp', [$validated['start_date'], $validated['end_date']])
            ->orderBy('timestamp')
            ->get();

        // Sample points based on interval
        $points = [];
        $lastTimestamp = null;

        foreach ($gpsLogs as $log) {
            if (!$lastTimestamp || $log->timestamp->diffInMinutes($lastTimestamp) >= $interval) {
                $points[] = [
                    'latitude' => (float) $log->latitude,
                    'longitude' => (float) $log->longitude,
                    'speed' => (float) $log->speed,
                    'timestamp' => $log->timestamp->toIso8601String(),
                ];
                $lastTimestamp = $log->timestamp;
            }
        }

        // Calculate summary
        $totalDistance = 0;
        for ($i = 1; $i < count($gpsLogs); $i++) {
            $totalDistance += $this->haversineDistance(
                $gpsLogs[$i - 1]->latitude,
                $gpsLogs[$i - 1]->longitude,
                $gpsLogs[$i]->latitude,
                $gpsLogs[$i]->longitude
            );
        }

        $duration = $gpsLogs->first() && $gpsLogs->last()
            ? $gpsLogs->first()->timestamp->diffInHours($gpsLogs->last()->timestamp)
            : 0;

        return response()->json([
            'points' => $points,
            'summary' => [
                'total_distance_km' => round($totalDistance, 2),
                'total_duration_hours' => round($duration, 2),
                'avg_speed' => $duration > 0 ? round($totalDistance / $duration, 2) : 0,
                'max_speed' => round($gpsLogs->max('speed'), 2),
            ],
        ]);
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
