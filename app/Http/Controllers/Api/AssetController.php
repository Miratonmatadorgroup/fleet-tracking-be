<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;


class AssetController extends Controller
{
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                // ASSET FIELDS
                'equipment_id' => 'required|string|unique:assets,equipment_id',
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

                // DRIVER FIELDS
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:drivers,email|required_without:phone',
                'phone' => 'nullable|string|max:20|required_without:email',
                'transport_mode' => 'required|string',
            ]);

            DB::beginTransaction();

            $driver = Driver::create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'transport_mode' => $validated['transport_mode'],
                'status' => 'inactive',
                'application_status' => 'approved',
            ]);

            $assetData = collect($validated)->except([
                'name',
                'email',
                'phone',
                'transport_mode'
            ])->toArray();

            $assetData['organization_id'] = $request->user()->organization_id;
            $assetData['status'] = 'offline';
            $assetData['driver_id'] = $driver->id;

            $asset = Asset::create($assetData);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'created',
                'entity_type' => Asset::class,
                'entity_id' => $asset->id,
                'new_values' => $asset->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return successResponse(
                'Asset and driver created successfully.',
                $asset->load(['driver', 'organization'])
            );
        } catch (\Illuminate\Validation\ValidationException $e) {

            return failureResponse($e->errors(), 422);
        } catch (\Throwable $th) {

            DB::rollBack();

            return failureResponse(
                'Failed to create asset and driver.',
                500,
                'server_error',
                $th
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {

            $user = $request->user();

            // Ensure asset belongs to the authenticated user
            $asset = Asset::whereHas('driver', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
                ->with('driver')
                ->findOrFail($id);

            $validated = $request->validate([
                // ASSET FIELDS
                'equipment_id' => 'sometimes|string|unique:assets,equipment_id,' . $asset->id,
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

                // DRIVER FIELDS
                'name' => 'sometimes|string|max:255',
                'email' => 'nullable|email|unique:drivers,email,' . $asset->driver->id,
                'phone' => 'nullable|string|max:20',
                'transport_mode' => 'sometimes|string',
            ]);

            DB::beginTransaction();

            // Separate driver data
            $driverData = collect($validated)->only([
                'name',
                'email',
                'phone',
                'transport_mode'
            ])->toArray();

            if (!empty($driverData)) {
                $asset->driver->update($driverData);
            }

            // Separate asset data
            $assetData = collect($validated)->except([
                'name',
                'email',
                'phone',
                'transport_mode'
            ])->toArray();

            if (!empty($assetData)) {
                $oldValues = $asset->getOriginal();
                $asset->update($assetData);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'updated',
                    'entity_type' => Asset::class,
                    'entity_id' => $asset->id,
                    'old_values' => $oldValues,
                    'new_values' => $asset->fresh()->toArray(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            DB::commit();

            return successResponse(
                'Asset and driver updated successfully.',
                $asset->fresh()->load(['driver', 'organization'])
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return failureResponse('Asset not found or unauthorized', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse($e->errors(), 422);
        } catch (\Throwable $th) {
            DB::rollBack();

            return failureResponse(
                'Failed to update asset.',
                500,
                'server_error',
                $th
            );
        }
    }

    public function myAssets(Request $request)
    {
        try {
            $userId = $request->user()->id;

            // Start the query
            $query = Asset::whereHas('driver', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->with(['driver', 'organization']);

            // =========================
            // FILTERS
            // =========================
            if ($request->filled('asset_type')) {
                $query->where('asset_type', $request->input('asset_type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('class')) {
                $query->where('class', $request->input('class'));
            }

            if ($request->filled('make')) {
                $query->where('make', 'like', '%' . $request->input('make') . '%');
            }

            if ($request->filled('model')) {
                $query->where('model', 'like', '%' . $request->input('model') . '%');
            }

            // =========================
            // PAGINATION (default 10)
            // =========================
            $perPage = $request->input('per_page', 10); // allows overriding per page if needed
            $assets = $query->paginate($perPage);

            return successResponse(
                'Your assets retrieved successfully.',
                $assets
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to retrieve your assets.',
                500,
                'server_error',
                $th
            );
        }
    }
// /////////////////////////////////////////////////////////////////////////////////

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
