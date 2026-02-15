<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Tracker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfficeAdminController extends Controller
{
    public function assignFleetManager(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        if (! $request->filled('email') && ! $request->filled('phone')) {
            return failureResponse('Provide either email or phone.', 422);
        }

        $officeAdmin = Auth::user();

        if (! $officeAdmin->hasRole('office_admin')) {
            return failureResponse('Unauthorized', 403);
        }

        $merchant = Merchant::where('user_id', $officeAdmin->id)->first();

        if (! $merchant) {
            return failureResponse('Office admin does not have a merchant account.', 404);
        }

        $user = User::where(function ($query) use ($request) {
            if ($request->filled('email')) {
                $query->where('email', $request->email);
            }

            if ($request->filled('phone')) {
                $query->orWhere('phone', $request->phone);
            }
        })->first();

        if (! $user) {
            return failureResponse('User not found.', 404);
        }

        if (
            $user->hasRole('fleet_manager') &&
            $user->merchant_id === $merchant->id
        ) {
            return failureResponse('User is already assigned as a fleet manager under your merchant.', 422);
        }

        if (
            $user->hasRole('fleet_manager') &&
            $user->merchant_id !== null &&
            $user->merchant_id !== $merchant->id
        ) {
            return failureResponse('User is already assigned to another merchant.', 422);
        }

        // Assign role
        $user->syncRoles(['fleet_manager']);

        $user->update([
            'merchant_id' => $merchant->id,
            'is_suspended' => false,
        ]);

        return successResponse('Fleet manager assigned successfully');
    }


    public function unassignFleetManager(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        if (! $request->filled('email') && ! $request->filled('phone')) {
            return failureResponse('Provide either email or phone.', 422);
        }

        $officeAdmin = Auth::user();

        if (! $officeAdmin->hasRole('office_admin')) {
            return failureResponse('Unauthorized', 403);
        }

        $merchant = Merchant::where('user_id', $officeAdmin->id)->first();

        if (! $merchant) {
            return failureResponse('Office admin does not have a merchant account.', 404);
        }

        $user = User::where(function ($query) use ($request) {
            if ($request->filled('email')) {
                $query->where('email', $request->email);
            }

            if ($request->filled('phone')) {
                $query->orWhere('phone', $request->phone);
            }
        })->first();

        if (! $user) {
            return failureResponse('User not found.', 404);
        }

        if (
            ! $user->hasRole('fleet_manager') ||
            $user->merchant_id !== $merchant->id
        ) {
            return failureResponse('User is already unassigned as fleet manager under your merchant.', 422);
        }

        // Remove role
        $user->removeRole('fleet_manager');

        $user->update([
            'merchant_id' => null,
        ]);

        return successResponse('Fleet manager unassigned successfully');
    }

    public function suspendFleetManager($userId)
    {
        $officeAdmin = Auth::user();

        if (! $officeAdmin->hasRole('office_admin')) {
            return failureResponse('Unauthorized', 403);
        }

        // Get merchant owned by office admin
        $merchant = Merchant::where('user_id', $officeAdmin->id)->first();

        if (! $merchant) {
            return failureResponse('Office admin does not have a merchant account.', 404);
        }

        $user = User::where('merchant_id', $merchant->id)
            ->where('id', $userId)
            ->whereHas('roles', fn($q) => $q->where('name', 'fleet_manager'))
            ->firstOrFail();

        if ($user->is_suspended) {
            return failureResponse('Fleet manager is already suspended.', 422);
        }

        $user->update([
            'is_suspended' => true,
        ]);

        return successResponse('Fleet manager suspended successfully.');
    }

    public function unsuspendFleetManager($userId)
    {
        $officeAdmin = Auth::user();

        if (! $officeAdmin->hasRole('office_admin')) {
            return failureResponse('Unauthorized', 403);
        }

        // Get merchant owned by office admin
        $merchant = Merchant::where('user_id', $officeAdmin->id)->first();

        if (! $merchant) {
            return failureResponse('Office admin does not have a merchant account.', 404);
        }

        $user = User::where('merchant_id', $merchant->id)
            ->where('id', $userId)
            ->whereHas('roles', fn($q) => $q->where('name', 'fleet_manager'))
            ->firstOrFail();

        if (! $user->is_suspended) {
            return failureResponse('Fleet manager is already unsuspended.', 422);
        }

        $user->update([
            'is_suspended' => false,
        ]);

        return successResponse('Fleet manager unsuspended successfully.');
    }

    public function viewMyTrackers(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = min($request->input('per_page', 20), 100);

            if (! $user->hasAnyRole(['office_admin', 'fleet_manager'])) {
                return failureResponse('Unauthorized', 403);
            }

            if ($user->hasRole('fleet_manager') && $user->is_suspended) {
                return failureResponse('Your account is suspended', 403);
            }

            // Get merchant
            if ($user->hasRole('office_admin')) {
                $merchant = Merchant::where('user_id', $user->id)->first();
            } else {
                // fleet_manager
                $merchant = Merchant::where('id', $user->merchant_id)->first();
            }

            if (! $merchant) {
                return failureResponse('Merchant not found.', 404);
            }

            $query = Tracker::with('merchant')
                ->where('merchant_id', $merchant->id);

            // Optional filter
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('serial_number', 'like', "%{$search}%")
                        ->orWhere('imei', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            }

            $trackers = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return successResponse('Trackers retrieved successfully', $trackers);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500);
        }
    }


    public function updateTrackersWithLabel(Request $request, $trackerId)
    {
        try {
            // Validate input
            $request->validate([
                'label' => 'required|string|max:255'
            ]);

            $user = Auth::user();

            // Only office_admin or fleet_manager allowed
            if (!$user->hasAnyRole(['office_admin', 'fleet_manager'])) {
                return failureResponse('Unauthorized', 403);
            }

            // Block suspended fleet managers
            if ($user->hasRole('fleet_manager') && $user->is_suspended) {
                return failureResponse('Your account is suspended', 403);
            }

            // Determine merchant_id based on role
            $merchantId = null;

            if ($user->hasRole('office_admin')) {
                $merchant = Merchant::where('user_id', $user->id)->first();
                if (!$merchant) {
                    return failureResponse('Merchant not found for this office admin.', 404);
                }
                $merchantId = $merchant->id;
            } elseif ($user->hasRole('fleet_manager')) {
                if (!$user->merchant_id) {
                    return failureResponse('You are not assigned to any merchant.', 422);
                }
                $merchantId = $user->merchant_id;
            }

            // Find tracker under this merchant
            $tracker = Tracker::where('id', $trackerId)
                ->where('merchant_id', $merchantId)
                ->firstOrFail();

            // Update label
            $tracker->update([
                'label' => $request->label
            ]);

            return successResponse('Tracker label updated successfully', $tracker);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse($e->errors(), 422);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500);
        }
    }

    public function myFleetManagers(Request $request)
    {
        $officeAdmin = Auth::user();

        if (! $officeAdmin->hasRole('office_admin')) {
            return failureResponse('Unauthorized', 403);
        }

        $merchant = Merchant::where('user_id', $officeAdmin->id)->first();

        if (! $merchant) {
            return failureResponse('Office admin does not have a merchant account.', 404);
        }

        $search = $request->query('search');
        $perPage = $request->query('per_page', 10); // default 10

        $fleetManagers = User::role('fleet_manager')
            ->where('merchant_id', $merchant->id)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'created_at',
                'is_suspended'
            ])
            ->latest()
            ->paginate($perPage);

        return successResponse('Fleet managers retrieved successfully', $fleetManagers);
    }
}
