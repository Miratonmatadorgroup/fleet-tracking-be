<?php

namespace App\Actions\BookRide;

use App\Models\Driver;
use App\Models\Discount;
use App\Models\RidePool;
use Illuminate\Support\Str;
use App\Mail\RideBookedEmail;
use App\Models\TransportMode;
use App\Services\TwilioService;
use App\Services\WalletService;
use App\Services\PricingService;
use App\Jobs\AssignNextDriverJob;
use App\Models\WalletTransaction;
use App\DTOs\BookRide\BookRideDTO;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\ExternalBankService;
use Illuminate\Database\QueryException;
use App\Events\BookRide\RideBookedEvent;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\Notifications\User\RideBookedNotification;
use App\Notifications\User\DriverRideAssignedNotification;

class BookRideAction
{
    /**
     * @return array ['ride' => RidePool, 'was_recently_created' => bool]
     */

    public function execute(BookRideDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {

            $user = $dto->user;
            $bankService = app(ExternalBankService::class);

            // Use the CheckWalletForRideService for wallet validation
            $walletService = app(\App\Services\CheckWalletForRideService::class);

            $token = $dto->estimate_token ?? "ride_estimate_{$user->id}";
            $estimatedCost = cache()->get("{$token}_cost");
            $pickup = cache()->get("{$token}_pickup");
            $dropoff = cache()->get("{$token}_dropoff");
            $duration = cache()->get("{$token}_duration");

            if (empty($pickup) || is_null($estimatedCost)) {
                throw new \Exception("Missing ride search data. Please perform a ride search first.");
            }

            if (empty($dropoff) && !empty($dto->usage_hours)) {
                $dropoff = $pickup;
            }

            // Apply discount if available
            $discount = Discount::where('is_active', true)
                ->where(
                    fn($q) => $q->where('applies_to_all', true)
                        ->orWhereHas('users', fn($uq) => $uq->where('user_id', $user->id))
                )
                ->where(
                    fn($q) => $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now())
                )
                ->first();

            $discountPercentage = $discount?->percentage ?? 0;
            $discountCost = round($estimatedCost - ($estimatedCost * ($discountPercentage / 100)), 2);

            //Wallet validation (external account, internal balance, merchant liquidity)
            $wallet = $walletService->validateWallet($user, $discountCost, $bankService);

            // Book ride
            $ride = RidePool::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'driver_id' => $dto->driver_id,
                    'transport_mode_id' => $dto->transport_mode_id,
                    'status' => RidePoolStatusEnums::BOOKED->value,
                ],
                [
                    'pickup_location' => is_array($pickup) ? json_encode($pickup) : $pickup,
                    'dropoff_location' => is_array($dropoff) ? json_encode($dropoff) : $dropoff,
                    'ride_date' => now(),
                    'duration' => $duration,
                    'estimated_cost' => $estimatedCost,
                    'discount_percentage' => $discountPercentage,
                    'discount_cost' => $discountCost,
                    'ride_pool_category' => null,
                    'payment_status' => RidePoolPaymentStatusEnums::UNPAID->value,
                ]
            );

            $wasRecentlyCreated = $ride->wasRecentlyCreated;

            if ($wasRecentlyCreated) {
                //Deduct from available_balance → pending_balance
                $wallet->available_balance -= $discountCost;
                $wallet->total_balance -= $discountCost;
                $wallet->pending_balance += $discountCost;
                $wallet->save();

                WalletTransaction::create([
                    'wallet_id'   => $wallet->id,
                    'user_id'     => $user->id,
                    'amount'      => $discountCost,
                    'type'        => WalletTransactionTypeEnums::DEBIT,
                    'status'      => WalletTransactionStatusEnums::PENDING,
                    'method'      => WalletTransactionMethodEnums::WALLET,
                    'description' => 'Ride booking',
                    'reference'   => WalletService::generateTransactionReference(),
                ]);



                RideBookedEvent::dispatch($ride);
                $user->notify(new RideBookedNotification($ride));

                if ($ride->driver_id) {
                    $ride->driver->notify(new DriverRideAssignedNotification($ride));
                }

                AssignNextDriverJob::dispatch($ride->id)
                    ->delay(now()->addMinutes(10));

                Log::info("Ride booked: user_id={$user->id}, ride_id={$ride->id}");
            }

            return [
                'ride' => $ride,
                'was_recently_created' => $wasRecentlyCreated,
            ];
        });
    }
}
