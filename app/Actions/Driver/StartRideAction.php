<?php

namespace App\Actions\Driver;

use Carbon\Carbon;
use App\Models\Driver;
use App\Models\Wallet;
use App\Models\RidePool;
use App\Models\Commission;
use App\Models\TransportMode;
use App\Services\WalletService;
use App\Enums\DriverStatusEnums;
use App\Models\WalletTransaction;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Events\Driver\RideStartedEvent;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Notifications\User\RideStartedNotification;
use App\Notifications\User\RideDurationTimeoutNotification;

class StartRideAction
{
    public function execute(Driver $driver, string $rideId): RidePool
    {
        return DB::transaction(function () use ($driver, $rideId) {

            $ride = RidePool::lockForUpdate()->findOrFail($rideId);

            if ($ride->driver_id !== $driver->id) {
                throw new \Exception("You are not assigned to this ride.");
            }

            $allowedStatuses = [
                RidePoolStatusEnums::IN_TRANSIT->value,
                RidePoolStatusEnums::ARRIVED->value,
            ];

            if (!in_array($ride->status->value, $allowedStatuses, true)) {
                throw new \Exception("This ride cannot be started.");
            }

            $startTime = now();
            $endTime = null;

            if (!empty($ride->duration)) {
                $endTime = $startTime->clone()->addHours($ride->duration);
            }

            $ride->update([
                'status'     => RidePoolStatusEnums::RIDE_STARTED->value,
                'start_time' => $startTime,
                'end_time'   => $endTime,
            ]);

            $driver->update([
                'status' => DriverStatusEnums::UNAVAILABLE->value,
            ]);

            $ride->user->notify(new RideStartedNotification($ride, $driver->user));
            $driver->user->notify(new RideStartedNotification($ride, $driver->user));

            if ($endTime) {
                dispatch(function () use ($ride) {
                    $this->monitorRideDuration($ride->id);
                })->delay($endTime);
            }

            RideStartedEvent::dispatch($ride, $driver->user);

            return $ride;
        });
    }

    private function monitorRideDuration(string $rideId)
    {
        DB::transaction(function () use ($rideId) {

            $ride = RidePool::lockForUpdate()->find($rideId);

            if (!$ride || $ride->status->value !== RidePoolStatusEnums::RIDE_STARTED->value) {
                return;
            }

            if ($ride->end_time && Carbon::now()->greaterThanOrEqualTo($ride->end_time)) {

                $ride->update([
                    'status'         => RidePoolStatusEnums::RIDE_ENDED->value,
                    'payment_status' => RidePoolPaymentStatusEnums::PAID->value,
                ]);

                if ($ride->driver) {
                    $ride->driver->update([
                        'status' => DriverStatusEnums::AVAILABLE->value,
                    ]);
                }

                $this->deductRideCostFromWallet($ride);

                $this->settleCommissions($ride);

                if ($ride->user) {
                    $ride->user->notify(new RideDurationTimeoutNotification($ride));
                }

                if ($ride->driver?->user) {
                    $ride->driver->user->notify(new RideDurationTimeoutNotification($ride));
                }
            }
        });
    }

    private function deductRideCostFromWallet(RidePool $ride)
    {
        $wallet = Wallet::lockForUpdate()
            ->where('user_id', $ride->user_id)
            ->first();

        if (!$wallet) {
            return;
        }

        $rideCost = $ride->estimated_cost ?? 0;

        $newPending = max(0, $wallet->pending_balance - $rideCost);

        $wallet->update([
            'pending_balance' => $newPending,
        ]);

        if ($rideCost > 0) {
            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $ride->user_id,
                'amount'      => $rideCost,
                'type'        => WalletTransactionTypeEnums::DEBIT,
                'method'      => WalletTransactionMethodEnums::WALLET,
                'status'      => WalletTransactionStatusEnums::SUCCESS,
                'reference'   => WalletService::generateTransactionReference(),
                'description' => "Ride payment for ride {$ride->id}",
            ]);
        }
    }

    /**
     * NEW: Release commissions to driver + partner
     */
    private function settleCommissions(RidePool $ride)
    {
        $total = (float) ($ride->estimated_cost ?? 0);

        if ($total <= 0) {
            return;
        }

        // Commission percentages from DB
        $driverPercentage  = Commission::where('role', 'driver')->latest()->value('percentage') ?? 10;
        $partnerPercentage = Commission::where('role', 'partner')->latest()->value('percentage') ?? 10;

        $driverCommission  = round(($driverPercentage / 100) * $total, 2);
        $partnerCommission = round(($partnerPercentage / 100) * $total, 2);

        $driver = $ride->driver;
        $driverUser = $driver->user;

        // CREDIT DRIVER
        if ($driverUser && $driverUser->wallet) {
            $wallet = $driverUser->wallet;
            $wallet->available_balance += $driverCommission;
            $wallet->total_balance += $driverCommission;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id'  => $wallet->id,
                'user_id'    => $driverUser->id,
                'amount'     => $driverCommission,
                'type'       => 'credit',
                'role'       => 'driver',
                'reference'  => WalletService::generateTransactionReference(),
                'description' => "Driver commission for ride {$ride->id}",
            ]);
        }

        // CHECK IF DRIVER BELONGS TO A PARTNER
        $transportMode = TransportMode::where('driver_id', $driver->id)->first();
        $partner = $transportMode?->partner;

        if ($partner && $partner->user && $partner->user->wallet) {

            $wallet = $partner->user->wallet;
            $wallet->available_balance += $partnerCommission;
            $wallet->total_balance += $partnerCommission;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id'  => $wallet->id,
                'user_id'    => $partner->user->id,
                'amount'     => $partnerCommission,
                'type'       => 'credit',
                'role'       => 'partner',
                'reference'  => WalletService::generateTransactionReference(),
                'description' => "Partner commission for ride {$ride->id}",
            ]);
        }
    }
}
