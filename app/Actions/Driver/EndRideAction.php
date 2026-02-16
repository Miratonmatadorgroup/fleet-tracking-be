<?php
namespace App\Actions\Driver;

use App\Models\Driver;
use App\Models\Wallet;
use App\Models\RidePool;
use App\Models\Commission;
use App\Services\WalletService;
use App\Enums\DriverStatusEnums;
use App\Models\WalletTransaction;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Notifications\User\RideEndedNotification;


class EndRideAction
{
    public function execute(Driver $driver, string $rideId): RidePool
    {
        return DB::transaction(function () use ($driver, $rideId) {

            $ride = RidePool::lockForUpdate()->findOrFail($rideId);

            if ($ride->driver_id !== $driver->id) {
                throw new \Exception("You are not assigned to this ride.");
            }

            if ($ride->status->value !== RidePoolStatusEnums::RIDE_STARTED->value) {
                throw new \Exception("This ride cannot be ended.");
            }

            $ride->update([
                'status' => RidePoolStatusEnums::RIDE_ENDED->value,
                'end_time' => now(),
                'payment_status' => RidePoolPaymentStatusEnums::PAID->value,
            ]);

            // Make driver available
            $driver->update([
                'status' => DriverStatusEnums::AVAILABLE->value,
            ]);

            // Deduct pending wallet balance
            $this->deductRideCostFromWallet($ride);

            // Pay commissions
            $this->settleCommissions($ride);

            // Send notifications
            if ($ride->user) {
                $ride->user->notify(new RideEndedNotification($ride));
            }

            if ($driver->user) {
                $driver->user->notify(new RideEndedNotification($ride));
            }

            return $ride;
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
    }

    private function settleCommissions(RidePool $ride)
    {
        $total = (float) ($ride->estimated_cost ?? 0);
        if ($total <= 0) {
            return;
        }

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

        // CREDIT PARTNER IF EXISTS
        $partner = optional($driver->transportMode)->partner;

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
