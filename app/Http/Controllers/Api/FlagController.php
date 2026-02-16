<?php

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use App\Models\RidePool;
use Illuminate\Http\Request;
use App\Models\TransportMode;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class FlagController extends Controller
{
    public function flagRide(Request $request)
    {
        $request->validate([
            'ride_id' => 'required|uuid|exists:ride_pools,id',
            'reason'  => 'required|string|max:255',
        ]);

        $ride = RidePool::findOrFail($request->ride_id);

        $ride->update([
            'is_flagged'  => true,
            'flag_reason' => $request->reason,
            'flagged_by'  => Auth::id(),
        ]);

        return successResponse('Ride flagged for review.');
    }

    public function flagTransportMode(Request $request)
    {
        $request->validate([
            'transport_mode_id' => 'required|uuid|exists:transport_modes,id',
            'reason'            => 'required|string|max:255',
        ]);

        $mode = TransportMode::findOrFail($request->transport_mode_id);

        $mode->update([
            'is_flagged'  => true,
            'flag_reason' => $request->reason,
            'flagged_by'  => Auth::id(),
        ]);

        return successResponse('Transport mode flagged');
    }

    public function flagDriver(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|uuid|exists:drivers,id',
            'reason'            => 'required|string|max:255',
        ]);

        $driver = Driver::findOrFail($request->driver_id);

        $driver->update([
            'is_flagged'  => true,
            'flag_reason' => $request->reason,
            'flagged_by'  => Auth::id(),
        ]);

        return successResponse('Driver flagged');
    }

    // UNFLAG
     public function unflagRide(Request $request)
    {
        $request->validate([
            'ride_id' => 'required|uuid|exists:ride_pools,id',
        ]);

        $ride = RidePool::findOrFail($request->ride_id);

        $ride->update([
            'is_flagged'  => false,
            'flag_reason' => null,
            'flagged_by'  => null,
        ]);

        return successResponse('Ride unflagged successfully.');
    }


    // ==========================
    // UNFLAG TRANSPORT MODE
    // ==========================
    public function unflagTransportMode(Request $request)
    {
        $request->validate([
            'transport_mode_id' => 'required|uuid|exists:transport_modes,id',
        ]);

        $mode = TransportMode::findOrFail($request->transport_mode_id);

        $mode->update([
            'is_flagged'  => false,
            'flag_reason' => null,
            'flagged_by'  => null,
        ]);

        return successResponse('Transport mode unflagged successfully.');
    }


    // ==========================
    // UNFLAG DRIVER
    // ==========================
    public function unflagDriver(Request $request)
    {
        $request->validate([
            'driver_id' => 'required|uuid|exists:drivers,id',
        ]);

        $driver = Driver::findOrFail($request->driver_id);

        $driver->update([
            'is_flagged'  => false,
            'flag_reason' => null,
            'flagged_by'  => null,
        ]);

        return successResponse('Driver unflagged successfully.');
    }
}
