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
use Illuminate\Validation\Rules\Enum;

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
        $request->validate([
            'imei' => 'required|string'
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $response = $this->trackerService
            ->unlockVehicle($imei);

        SendWebhookJob::dispatch(
            $client,
            'vehicle.unlock',
            $response

        );

        return successResponse(
            'Unlock command sent',
            $response
        );
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

        $tracker = Tracker::where('imei', $imei)->first();

        if (! $tracker) {
            return failureResponse(
                'Tracker not found.',
                404
            );
        }

        $asset = Asset::where('tracker_id', $tracker->id)->first();

        if (! $asset) {
            return failureResponse(
                'No asset assigned to this tracker.',
                404
            );
        }

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

        $geofence->assets()->attach($asset->id);
        SendWebhookJob::dispatch(
            $client,
            'geofence.created',
            $geofence->load('assets')->toArray()
        );

        return successResponse(
            'Geofence created successfully.',
            $geofence->load('assets')
        );
    }

    public function updateGeofence(Request $request, string $id)
    {
        $request->validate([

            'imei' => 'required|string',

            'name' => 'required|string',

            'latitude' => 'required|numeric',

            'longitude' => 'required|numeric',

            'radius' => 'required|integer|min:50',

            'action' => ['required', new Enum(GeoFenceActionTypeEnums::class)],

        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $client = $this->authorizeImei($request, $imei);

        $tracker = Tracker::where('imei', $imei)->first();

        $asset = Asset::where('tracker_id', $tracker->id)->first();

        $geofence = Geofence::where('id', $id)
            ->whereHas('assets', function ($q) use ($asset) {
                $q->where('assets.id', $asset->id);
            })
            ->first();

        if (! $geofence) {

            return failureResponse(
                'Geofence not found.',
                404
            );
        }

        $geofence->update([
            'name' => $request->name,
            'coordinates' => [
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
            ],
            'radius_meters' => (int) $request->radius,
            'action' => $request->action,
        ]);

        SendWebhookJob::dispatch(
            $client,
            'geofence.updated',
            $geofence->fresh()->load('assets')->toArray()
        );

        return successResponse(
            'Geofence updated successfully.',
            $geofence->fresh()->load('assets')
        );
    }

    public function geofences(Request $request)
    {
        $request->validate([
            'imei' => 'required|string'
        ]);

        $imei = preg_replace('/\D/', '', $request->imei);

        $this->authorizeImei($request, $imei);

        $tracker = Tracker::where('imei', $imei)->first();

        $asset = Asset::where('tracker_id', $tracker->id)->first();

        $geofences = Geofence::with('assets')

            ->whereHas('assets', function ($q) use ($asset) {

                $q->where('assets.id', $asset->id);
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

        $tracker = Tracker::where('imei', $imei)->first();

        $asset = Asset::where('tracker_id', $tracker->id)->first();

        $geofence = Geofence::where('id', $id)
            ->whereHas('assets', function ($q) use ($asset) {
                $q->where('assets.id', $asset->id);
            })
            ->first();

        if (! $geofence) {
            return failureResponse(
                'Geofence not found.',
                404
            );
        }

        $payload = $geofence
            ->load('assets')
            ->toArray();

        $geofence->assets()->detach();
        $geofence->delete();
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
