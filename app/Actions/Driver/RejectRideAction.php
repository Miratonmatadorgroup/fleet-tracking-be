<?php

namespace App\Actions\Driver;

use Exception;
use App\Models\Driver;
use App\Models\RidePool;
use App\Enums\DriverStatusEnums;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Notifications\User\DriverRejectedRideNotification;
use App\Notifications\User\RideRejectedByDriverNotification;

class RejectRideAction
{
    public function execute(Driver $driver, string $rideId): RidePool
    {
        return DB::transaction(function () use ($driver, $rideId) {

            $ride = RidePool::lockForUpdate()->findOrFail($rideId);

            if ($ride->driver_id !== $driver->id) {
                throw new Exception("You are not assigned to this ride.");
            }

            if (!in_array($ride->status->value, [
                RidePoolStatusEnums::IN_TRANSIT->value,
                RidePoolStatusEnums::ARRIVED->value,
                RidePoolStatusEnums::BOOKED->value,
            ])) {
                throw new Exception("You can only reject a ride that is currently in transit or has arrived.");
            }

            Log::info("Driver rejecting ride", [
                'ride_id' => $ride->id,
                'driver_id' => $driver->id
            ]);

            $user   = $ride->user;
            $wallet = $user->wallet;

            // Refund the user
            if ($wallet) {
                $wallet->pending_balance -= $ride->estimated_cost;
                $wallet->available_balance += $ride->estimated_cost;
                $wallet->total_balance += $ride->estimated_cost;
                $wallet->save();
            }

            // Update ride status
            $ride->update([
                'status'         => RidePoolStatusEnums::REJECTED->value,
                'payment_status' => RidePoolPaymentStatusEnums::CANCELLED->value,
            ]);

            // Make driver available
            $driver->update([
                'status' => DriverStatusEnums::AVAILABLE->value,
            ]);

            $user->notify(new RideRejectedByDriverNotification($ride));
            $driver->notify(new DriverRejectedRideNotification($ride));

            return $ride;
        });
    }
}
