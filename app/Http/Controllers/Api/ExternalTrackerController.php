<?php

namespace App\Http\Controllers\Api;

use App\Enums\GeoFenceActionTypeEnums;
use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookJob;
use App\Models\ApiClient;
use App\Models\ApiClientAsset;
use App\Models\Asset;
use App\Models\Geofence;
use App\Models\Tracker;
use App\Services\TrackerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;

class ExternalTrackerController extends Controller
{
    //
    public function __construct(
        protected TrackerService $trackerService
    ) {}

    public function tracking(Request $request)
    {
        $request->validate([
            'imei' => 'required|string'
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $response = $this->trackerService
            ->getLastPosition([$imei]);

        SendWebhookJob::dispatch(
            $client,
            'tracking.updated',
            $response

        );

        return successResponse(
            'Tracking successful',
            $response
        );
    }

    public function shutdown(Request $request)
    {
        $request->validate([
            'imei' => 'required|string'
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $response = $this->trackerService
            ->lockVehicle($imei);

        SendWebhookJob::dispatch(
            $client,
            'vehicle.shutdown',
            $response

        );

        return successResponse(
            'Shutdown command sent',
            $response
        );
    }

    public function unlock(Request $request)
    {
        $request->validate(['imei' => 'required|string']);
        $imei = preg_replace('/\D/', '', $request->imei);
        $client = $this->authorizeImei($request, $imei);
        $response = $this->trackerService->unlockVehicle($imei);
        SendWebhookJob::dispatch($client, 'vehicle.unlock', $response);
        return successResponse('Unlock command sent', $response);
    }

    public function createGeofence(Request $request)
    {
        $request->validate([
            'imei'      => 'required|string',
            'name'      => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius'    => 'required|integer|min:50',
            'action'    => ['required', new Enum(GeoFenceActionTypeEnums::class)],
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $tracker = Tracker::where('imei', $imei)
            ->where('user_id', $client->customer_id)
            ->first();

        if (! $tracker) {
            return failureResponse(
                'Tracker not found.',
                404
            );
        }

        try {

            $geofence = DB::transaction(function () use (
                $client,
                $request,
                $tracker
            ) {

                $geofence = Geofence::create([

                    'user_id' => $client->customer_id,

                    'organization_id' => null,

                    'name' => $request->name,

                    'coordinates' => [
                        'latitude' => (float) $request->latitude,
                        'longitude' => (float) $request->longitude,
                    ],

                    'radius_meters' => (int) $request->radius,

                    'action' => $request->action,

                    'is_active' => true,

                ]);

                $geofence->trackers()->attach($tracker->id);

                return $geofence->load('trackers');
            });
        } catch (\Throwable $e) {

            Log::error('Developer geofence creation failed', [

                'customer_id' => $client->customer_id,
                'imei' => $tracker->imei,
                'error' => $e->getMessage(),

            ]);

            return failureResponse(
                'Unable to create geofence.',
                500
            );
        }

        SendWebhookJob::dispatch(
            $client,
            'geofence.created',
            $geofence->toArray()
        );

        return successResponse(
            'Geofence created successfully.',
            $geofence
        );
    }

    public function updateGeofence(Request $request, string $id)
    {
        $request->validate([
            'imei'      => 'required|string',
            'name'      => 'required|string',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius'    => 'required|integer|min:50',
            'action'    => ['required', new Enum(GeoFenceActionTypeEnums::class)],
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $tracker = Tracker::where('imei', $imei)
            ->where('user_id', $client->customer_id)
            ->first();

        if (! $tracker) {
            return failureResponse(
                'Tracker not found.',
                404
            );
        }

        $geofence = Geofence::where('id', $id)
            ->where('user_id', $client->customer_id)
            ->whereHas('trackers', function ($q) use ($tracker) {
                $q->where('trackers.id', $tracker->id);
            })
            ->first();

        if (! $geofence) {
            return failureResponse(
                'Geofence not found.',
                404
            );
        }

        try {

            DB::transaction(function () use ($geofence, $request) {

                $geofence->update([

                    'name' => $request->name,

                    'coordinates' => [
                        'latitude' => (float) $request->latitude,
                        'longitude' => (float) $request->longitude,
                    ],

                    'radius_meters' => (int) $request->radius,

                    'action' => $request->action,

                ]);
            });
        } catch (\Throwable $e) {

            Log::error('Developer geofence update failed', [

                'geofence_id' => $id,
                'error' => $e->getMessage(),

            ]);

            return failureResponse(
                'Unable to update geofence.',
                500
            );
        }

        $geofence = $geofence->fresh()->load('trackers');

        SendWebhookJob::dispatch(
            $client,
            'geofence.updated',
            $geofence->toArray()
        );

        return successResponse(
            'Geofence updated successfully.',
            $geofence
        );
    }

    public function geofences(Request $request)
    {
        $request->validate([
            'imei' => 'required|string'
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $tracker = Tracker::where('imei', $imei)
            ->where('user_id', $client->customer_id)
            ->first();

        if (! $tracker) {
            return failureResponse(
                'Tracker not found.',
                404
            );
        }

        $geofences = Geofence::with('trackers')

            ->where('user_id', $client->customer_id)

            ->whereHas('trackers', function ($q) use ($tracker) {

                $q->where('trackers.id', $tracker->id);
            })

            ->paginate(
                $request->integer('per_page', 20)
            );

        return successResponse(
            'Geofences retrieved successfully.',
            $geofences
        );
    }

    public function deleteGeofence(Request $request, string $id)
    {
        $request->validate([
            'imei' => 'required|string'
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $tracker = Tracker::where('imei', $imei)
            ->where('user_id', $client->customer_id)
            ->first();

        if (! $tracker) {
            return failureResponse(
                'Tracker not found.',
                404
            );
        }

        $geofence = Geofence::where('id', $id)
            ->where('user_id', $client->customer_id)
            ->whereHas('trackers', function ($q) use ($tracker) {
                $q->where('trackers.id', $tracker->id);
            })
            ->first();

        if (! $geofence) {
            return failureResponse(
                'Geofence not found.',
                404
            );
        }

        $payload = $geofence
            ->load('trackers')
            ->toArray();

        try {

            DB::transaction(function () use ($geofence) {

                $geofence->trackers()->detach();

                $geofence->delete();
            });
        } catch (\Throwable $e) {

            Log::error('Developer geofence deletion failed', [

                'geofence_id' => $id,
                'error' => $e->getMessage(),

            ]);

            return failureResponse(
                'Unable to delete geofence.',
                500
            );
        }

        SendWebhookJob::dispatch(
            $client,
            'geofence.deleted',
            $payload
        );

        return successResponse(
            'Geofence deleted successfully.'
        );
    }

    public function mileage(Request $request)
    {
        $request->validate([
            'imei' => 'required|string',
            'startday' => 'required|date_format:Y-m-d',
            'endday' => 'required|date_format:Y-m-d',
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $response = $this->trackerService
            ->getMileageDetail(
                $imei,
                $request->startday,
                $request->endday
            );

        SendWebhookJob::dispatch(
            $client,
            'mileage.updated',
            $response
        );

        return successResponse(
            'Mileage retrieved',
            $response
        );
    }

    public function assignTrackerDev(Request $request)
    {
        $request->validate([
            'customer_id'  => 'required|uuid|exists:users,id',
            'serial_number' => 'required|string',
            'label'        => 'nullable|string|max:100',
        ]);
        try {

            DB::transaction(function () use ($request) {
                /* GET API CLIENT */
                $client = ApiClient::where(
                    'customer_id',
                    $request->customer_id
                )
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $client) {
                    throw new RuntimeException(
                        'Developer API client not found.'
                    );
                }
                /* GET TRACKER */
                $tracker = Tracker::where(
                    'serial_number',
                    $request->serial_number
                )
                    ->lockForUpdate()
                    ->first();

                if (! $tracker) {
                    throw new RuntimeException(
                        'Tracker not found.'
                    );
                }

                if ($tracker->is_assigned) {
                    throw new RuntimeException(
                        'Tracker already assigned.'
                    );
                }
                /* Activate tracker */
                $tracker->update([

                    'status'      => 'active',

                    'user_id'     => $client->customer_id,

                    'label'       => $request->label,

                    'asset_id'    => null,

                    'is_assigned' => true,

                    'is_sold'     => true,

                ]);

                /* Map Tracker to API Client */
                ApiClientAsset::updateOrCreate(

                    [
                        'api_client_id' => $client->id,

                        'imei' => $tracker->imei,
                    ],

                    [

                        'serial_number' => $tracker->serial_number,

                        'label' => $request->label,

                    ]
                );
            });

            return successResponse(
                'Tracker assigned successfully.'
            );
        } catch (\Throwable $e) {

            return failureResponse(
                $e->getMessage(),
                422
            );
        }
    }

    private function authorizeImei(Request $request, string $imei): ApiClient
    {
        $client = $request->attributes->get('api_client');

        $allowed = ApiClientAsset::where('api_client_id', $client->id)
            ->where('imei', $imei)
            ->exists();

        if (! $allowed) {
            abort(response()->json([
                'success' => false,
                'message' => 'This device is not assigned to your API account.',
            ], 403));
        }

        return $client;
    }
}
