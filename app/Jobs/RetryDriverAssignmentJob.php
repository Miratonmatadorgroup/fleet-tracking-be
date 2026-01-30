<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use App\Services\DriverService;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\Log;
use App\Enums\DeliveryAssignmentLogsEnums;
use App\Services\DeliveryAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Delivery\DeliveryAssignedEvent;

class RetryDriverAssignmentJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(
        DriverService $driverService,
        DeliveryAssignmentService $assignmentService
    ): void {
        $deliveries = Delivery::where('status', DeliveryStatusEnums::QUEUED->value)->get();

        foreach ($deliveries as $delivery) {
            // Skip if driver is already assigned
            if ($delivery->driver_id) {
                Log::info("RetryJob: Delivery {$delivery->id} already has driver {$delivery->driver_id}, skipping.");
                continue;
            }

            $driver = $driverService->findNearestAvailable($delivery);

            if ($driver) {
                Log::info("RetryJob: Driver {$driver->id} assigned to Delivery {$delivery->id}");

                $delivery->status = DeliveryStatusEnums::BOOKED->value;
                $delivery->driver_assigned_at = now();
                $delivery->save();

                //Fire DeliveryAssignedEvent with SUCCESS
                event(new DeliveryAssignedEvent($delivery, $driver, DeliveryAssignmentLogsEnums::SUCCESS));

                // Notify parties
                $payment = Payment::where('delivery_id', $delivery->id)->first();
                $assignmentService->notifyParties($delivery, $driver, $payment);
            } else {
                Log::info("RetryJob: Still no driver for Delivery {$delivery->id}");

                //Fire DeliveryAssignedEvent with FAILED (not queued again, just log attempt)
                event(new DeliveryAssignedEvent($delivery, null, DeliveryAssignmentLogsEnums::FAILED));
            }
        }
    }
}
