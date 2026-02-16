<?php

namespace App\Services;


use App\Models\User;
use App\Models\Delivery;
use App\Models\ApiClient;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\Delivery\DeliveryCompleted;
use App\Enums\FundsReconcilationStatusEnums;
use App\Notifications\User\DeliveryCompletedInAppNotification;
use App\Notifications\Admin\DeliveryMarkedCompletedNotification;

class AdminDeliveryCompletionService
{
    public static function completeDeliveriesWithBankReference(): array
    {
        $deliveries = Delivery::query()
            ->where('status', DeliveryStatusEnums::DELIVERED)
            ->whereHas('payment', function ($q) {
                $q->where('reference', 'LIKE', 'BANK%');
            })
            ->whereHas('fundReconciliation', function ($q) {
                $q->where('status', FundsReconcilationStatusEnums::PARTLY_PAID_OFF);
            })
            ->with(['driver.user', 'partner', 'investor'])
            ->get();

        $count = 0;

        DB::transaction(function () use ($deliveries, &$count) {
            foreach ($deliveries as $delivery) {
                $delivery->status = DeliveryStatusEnums::COMPLETED;
                $delivery->save();

                dispatch(function () use ($delivery) {
                    event(new DeliveryCompleted($delivery));
                })->afterResponse();


                WalletService::creditCommissions($delivery->driver->user, $delivery);

                //Notify Driver
                $delivery->driver->user->notify(new DeliveryCompletedInAppNotification($delivery, 'driver'));

                //Notify Admins
                User::role('admin')->get()->each(function (User $admin) use ($delivery) {
                    $admin->notify(new DeliveryMarkedCompletedNotification($delivery));
                });

                $count++;
            }
        });

        if ($count > 0) {
            return [
                'count'   => $count,
                'message' => "{$count} deliveries marked as completed and commissions released.",
            ];
        }

        return [
            'count'   => 0,
            'message' => "No new deliveries found to complete. All eligible deliveries are already completed.",
        ];
    }

    public static function completeDeliveriesSingleExternalUser(string $apiClientId): array
    {
        //Fetch the API client
        $apiClient = ApiClient::find($apiClientId);

        if (!$apiClient) {
            return [
                'count'   => 0,
                'message' => "API client not found.",
            ];
        }

        $deliveries = Delivery::query()
            ->where('api_client_id', $apiClientId)
            ->where('status', DeliveryStatusEnums::DELIVERED)
            ->whereHas('payment', function ($q) use ($apiClientId) {
                $q->where('api_client_id', $apiClientId);
            })
            ->whereHas('fundReconciliation', function ($q) {
                $q->where('status', FundsReconcilationStatusEnums::PARTLY_PAID_OFF);
            })
            ->with(['driver.user', 'partner', 'investor', 'transportMode.partner.user'])
            ->get();

        $count = 0;

        DB::transaction(function () use ($deliveries, &$count) {
            foreach ($deliveries as $delivery) {
                Log::info('Partner check during delivery completion', [
                    'delivery_id' => $delivery->id,
                    'transport_mode_id' => $delivery->transport_mode_id,
                    'transport_mode_partner_user' => $delivery->transportMode?->partner?->user?->id,
                    'driver_partner_user' => $delivery->driver?->partner?->user?->id,
                ]);
                $delivery->status = DeliveryStatusEnums::COMPLETED;
                $delivery->save();

                // event(new DeliveryCompleted($delivery));

                dispatch(function () use ($delivery) {
                    event(new DeliveryCompleted($delivery));
                })->afterResponse();


                if ($delivery->driver && $delivery->driver->user) {
                    WalletService::creditCommissions($delivery->driver->user, $delivery);

                    // Notify driver
                    $delivery->driver->user->notify(
                        new DeliveryCompletedInAppNotification($delivery, 'driver')
                    );
                }

                // Notify admins
                User::role('admin')->get()->each(function (User $admin) use ($delivery) {
                    $admin->notify(new DeliveryMarkedCompletedNotification($delivery));
                });

                $count++;
            }
        });

        return $count > 0
            ? [
                'count'   => $count,
                'message' => "{$count} deliveries marked as completed for API client '{$apiClient->name}'.",
            ]
            : [
                'count'   => 0,
                'message' => "No deliveries found for API client '{$apiClient->name}' with status 'delivered'.",
            ];
    }
}
