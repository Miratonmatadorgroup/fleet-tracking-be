<?php

namespace App\Http\Controllers\Api;

use App\Models\Tracker;
use App\Models\Merchant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\TrackerStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\TransactionPinService;

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
        ]);

        return successResponse('Tracker activated successfully');
    }

    public function assignRange(Request $request)
    {
        $request->validate([
            'merchant_code' => 'required|exists:merchants,merchant_code',
            'start_serial' => 'required|string',
            'end_serial' => 'required|string',
        ]);

        $merchant = Merchant::where('merchant_code', $request->merchant_code)
            ->where('status', 'approved')
            ->firstOrFail();

        $trackers = Tracker::whereBetween('serial_number', [
            $request->start_serial,
            $request->end_serial
        ])
            ->where('status', 'inactive')
            ->lockForUpdate()
            ->get();

        if ($trackers->isEmpty()) {
            return failureResponse('No available trackers in this range');
        }

        DB::transaction(function () use ($request, $merchant) {
            Tracker::whereBetween('serial_number', [
                $request->start_serial,
                $request->end_serial
            ])
                ->where('status', 'inactive')
                ->update([
                    'merchant_id' => $merchant->id,
                    'status' => 'assigned',
                ]);
        });

        return successResponse(
            count($trackers) . ' trackers assigned to merchant'
        );
    }
}
