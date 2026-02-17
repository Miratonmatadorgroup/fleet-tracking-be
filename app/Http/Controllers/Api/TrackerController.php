<?php

namespace App\Http\Controllers\Api;

use App\Enums\MerchantStatusEnums;
use App\Enums\TrackerStatusEnums;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Merchant;
use App\Models\Tracker;
use App\Services\TrackerService;
use App\Services\TransactionPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrackerController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        try {
            $request->validate([
                'trackers' => 'required|array|min:1',
                'trackers.*.serial_number' => 'required|string',
                'trackers.*.imei' => 'required|string',
            ]);

            $user = Auth::user();
            $created = 0;
            $updated = 0;

            DB::transaction(function () use ($request, $user, &$created, &$updated) {
                foreach ($request->trackers as $data) {

                    $tracker = Tracker::updateOrCreate(
                        [
                            'serial_number' => $data['serial_number'],
                        ],
                        [
                            'imei' => $data['imei'],
                            'status' => TrackerStatusEnums::INACTIVE,
                            'is_assigned' => false,
                            'inventory_by' => $user->id,
                            'inventory_at' => now(),
                        ]
                    );

                    $tracker->wasRecentlyCreated ? $created++ : $updated++;
                }
            });

            return successResponse(
                'Trackers successfully stored or updated',
                [
                    'created' => $created,
                    'updated' => $updated,
                    'total' => $created + $updated,
                ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse(
                $e->errors(),
                422,
                'validation_error',
                $e
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to store trackers',
                500,
                'tracker_store_error',
                $th
            );
        }
    }

    public function index(Request $request)
    {
        try {
            // Optional: Pagination parameters
            $perPage = $request->input('per_page', 20); // default 20

            $trackers = Tracker::with(['user', 'merchant', 'inventoriedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return successResponse(
                'Trackers retrieved successfully',
                $trackers
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to retrieve trackers',
                500,
                'tracker_index_error',
                $th
            );
        }
    }

    public function destroy(Tracker $tracker)
    {
        try {
            if ($tracker->is_assigned) {
                return failureResponse(
                    'Assigned trackers cannot be deleted',
                    422
                );
            }

            $tracker->delete();

            return successResponse(
                'Tracker deleted successfully'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to delete tracker',
                500,
                'tracker_delete_error',
                $th
            );
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'tracker_ids' => 'required|array|min:1',
                'tracker_ids.*' => 'uuid|exists:trackers,id',
            ]);

            $deletedCount = Tracker::whereIn('id', $request->tracker_ids)
                ->where('is_assigned', false)
                ->delete();

            return successResponse(
                'Trackers deleted successfully',
                [
                    'requested' => count($request->tracker_ids),
                    'deleted' => $deletedCount,
                ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse(
                $e->errors(),
                422,
                'validation_error',
                $e
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to delete trackers',
                500,
                'bulk_tracker_delete_error',
                $th
            );
        }
    }

    public function activate(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'label'           => 'required|string|max:100',
            'transaction_pin' => 'required|digits:4',
        ]);

        $user = Auth::user();

        $tracker = Tracker::where('serial_number', $request->serial_number)
            ->where('status', 'inactive')
            ->first();

        if (! $tracker) {
            return failureResponse('Invalid or already activated tracker');
        }

        // PIN logic
        $pinService = app(TransactionPinService::class);

        try {
            if ($user->transaction_pin) {
                // Verify existing PIN
                $pinService->checkPin($user, $request->transaction_pin);
            } else {
                // First-time PIN creation
                $pinService->createPin($user, $request->transaction_pin);
            }
        } catch (\Exception $e) {
            return failureResponse($e->getMessage());
        }

        // Activate tracker
        $tracker->update([
            'status' => 'active',
            'user_id' => $user->id,
            'label'   => $request->label,
        ]);

        return successResponse('Tracker activated successfully');
    }

    public function assignRange(Request $request)
    {
        $request->validate([
            'merchant_code' => 'required|exists:merchants,merchant_code',
            'start_serial'  => 'required|string',
            'end_serial'    => 'required|string',
        ]);

        try {
            $merchant = Merchant::where('merchant_code', $request->merchant_code)->first();

            if (! $merchant) {
                return failureResponse('Merchant not found');
            }

            if ($merchant->status === MerchantStatusEnums::SUSPENDED) {
                return failureResponse('Suspended merchant cannot receive trackers', 422);
            }

            DB::transaction(function () use ($request, $merchant) {

                $trackers = Tracker::whereBetween('serial_number', [
                    $request->start_serial,
                    $request->end_serial
                ])
                    ->where('status', TrackerStatusEnums::INACTIVE)
                    ->lockForUpdate()
                    ->get();

                if ($trackers->isEmpty()) {
                    throw new \RuntimeException('No available trackers in this range');
                }

                Tracker::whereIn('id', $trackers->pluck('id'))
                    ->update([
                        'merchant_id' => $merchant->id,
                        'status'      => TrackerStatusEnums::ASSIGNED,
                        'is_assigned' => true,
                    ]);

                // Approve merchant after successful assignment
                if ($merchant->status !== MerchantStatusEnums::APPROVED) {
                    $merchant->approve(Auth::user());
                }
            });

            return successResponse(
                'Trackers assigned to merchant successfully'
            );
        } catch (\RuntimeException $e) {
            return failureResponse($e->getMessage(), 422);
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to assign trackers',
                500,
                'tracker_assignment_error',
                $th
            );
        }
    }

    public function myTrackers(Request $request)
    {
        try {
            $user = Auth::user();

            $perPage = $request->input('per_page', 20);

            $query = Tracker::with([
                'merchant:id,name,email',        // adjust fields as needed
            ])
                ->where('user_id', $user->id);

            // Optional: filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Optional: search by serial, imei or label
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('serial_number', 'like', "%{$search}%")
                        ->orWhere('imei', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            }

            $trackers = $query
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return successResponse(
                'Your trackers retrieved successfully',
                $trackers
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to retrieve trackers',
                500,
                'my_tracker_error',
                $th
            );
        }
    }


    public function tracking(Request $request, TrackerService $trackerService)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id'
        ]);

        $asset = Asset::with('tracker')->findOrFail($request->asset_id);

        if (! $asset->tracker) {
            return failureResponse('No tracker assigned to this asset');
        }

        $deviceId = $asset->tracker->imei; // or serial_number depending on provider

        $response = $trackerService->getLastPosition([$deviceId]);

        // Optionally store last known position
        if (isset($response['records'][0])) {

            $position = $response['records'][0];

            $asset->update([
                'last_known_lat' => $position['silent'],
                'last_known_lng' => $position['callon'],
                'last_ping_at'   => now(),
            ]);
        }

        return successResponse('Live tracking data', $response);
    }


    public function remoteShutdown(Request $request, TrackerService $trackerService)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id'
        ]);

        $asset = Asset::with('tracker')->findOrFail($request->asset_id);

        if (! $asset->tracker) {
            return failureResponse('No tracker assigned');
        }

        $response = $trackerService->lockVehicle(
            $asset->tracker->imei,
            1 // or store device_type in tracker table
        );

        // Log command
        $asset->remoteCommands()->create([
            'command' => 'lock',
            'response' => json_encode($response),
            'status' => $response['status'] ?? null
        ]);

        return successResponse('Shutdown command sent', $response);
    }


    public function geoFencing(Request $request, TrackerService $trackerService)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'required|integer'
        ]);

        $asset = Asset::with('tracker')->findOrFail($request->asset_id);

        $response = $trackerService->addGeofence(
            $asset->tracker->imei,
            $request->latitude,
            $request->longitude,
            $request->radius
        );

        return successResponse('Geofence added', $response);
    }

    public function assignTrackerToAsset(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'tracker_id' => 'required|exists:trackers,id'
        ]);

        $asset = Asset::findOrFail($request->asset_id);
        $tracker = Tracker::where('id', $request->tracker_id)
            ->where('status', 'active')
            ->firstOrFail();

        $asset->update([
            'tracker_id' => $tracker->id
        ]);

        return successResponse('Tracker assigned to asset');
    }
}
