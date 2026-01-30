<?php

namespace App\Actions\Delivery;

use App\Models\Driver;
use App\Models\Delivery;
use App\Services\DriverService;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Enums\DeliveryStatusEnums;
use App\Mail\DeliveryAssignedToUser;
use App\Mail\DriverReassignedToUser;
use Illuminate\Support\Facades\Mail;
use App\Mail\DriverAssignedToDelivery;
use App\Mail\DriverUnassignedFromDelivery;
use Illuminate\Validation\ValidationException;
use App\Notifications\User\DriverAssignedToDeliveryNotification;
use App\Notifications\User\DeliveryAssignedToCustomerNotification;
use App\Notifications\User\DeliveryReassignedToCustomerNotification;
use App\Notifications\User\DriverUnassignedFromDeliveryNotification;

class AssignDeliveryToDriverAction
{
    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii,
        protected DriverService $driverService
    ) {}
    public function execute(string $trackingNumber, string $identifier): Delivery
    {
        $delivery = Delivery::with('customer')
            ->where('tracking_number', $trackingNumber)
            ->firstOrFail();

        if (in_array($delivery->status, [
            DeliveryStatusEnums::DELIVERED,
            DeliveryStatusEnums::COMPLETED,
            DeliveryStatusEnums::CANCELLED,
        ])) {
            throw ValidationException::withMessages([
                'tracking_number' => [
                    "Cannot assign driver. Delivery status is '{$delivery->status->value}'."
                ]
            ]);
        }

        $driver = Driver::with(['user', 'transportModeDetails.partner'])
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('whatsapp_number', $identifier);
            })
            ->first();

        if (!$driver) {
            throw ValidationException::withMessages([
                'identifier' => ['No driver found with the provided identifier.']
            ]);
        }

        if (!in_array($driver->status, [
            DriverStatusEnums::ACTIVE,
            DriverStatusEnums::AVAILABLE
        ])) {
            throw ValidationException::withMessages([
                'identifier' => ['Driver is not available for assignment.']
            ]);
        }

        //LOAD TRANSPORT MODE ONCE
        $transportMode = $driver->transportModeDetails;

        if (!$transportMode) {
            throw ValidationException::withMessages([
                'identifier' => ['Driver has no transport mode assigned.']
            ]);
        }

        //VALIDATE DRIVER + MODE VIA SERVICE
        $this->driverService->checkDriverAndModeStatus($transportMode);

        $driverChanged = $delivery->driver_id
            && $delivery->driver_id !== $driver->id;

        // Release old driver if reassigned
        if ($driverChanged) {
            $oldDriver = Driver::with('user')->find($delivery->driver_id);

            if ($oldDriver) {
                $this->notifyDriverUnassigned($oldDriver, $delivery);
                $oldDriver->status = DriverStatusEnums::AVAILABLE;
                $oldDriver->save();
            }
        }

        // Assign new driver
        $delivery->driver_id = $driver->id;
        $delivery->driver_assigned_at = now();

        // Partner + transport mode linkage
        $delivery->transport_mode_id = $transportMode->id;
        $delivery->partner_id = $transportMode->partner_id;

        // Update delivery status
        if ($delivery->status === DeliveryStatusEnums::QUEUED) {
            $delivery->status = DeliveryStatusEnums::BOOKED;
        }

        $delivery->save();

        // Update driver status
        $driver->status = DriverStatusEnums::UNAVAILABLE;
        $driver->save();

        // Notifications
        $this->notifyDriverAssigned($driver, $delivery);
        $this->notifyCustomer($delivery, $driver, $driverChanged);

        return $delivery->fresh();
    }
    // public function execute(string $trackingNumber, string $identifier): Delivery
    // {
    //     $delivery = Delivery::with('customer')->where('tracking_number', $trackingNumber)->firstOrFail();

    //     if (in_array($delivery->status, [
    //         DeliveryStatusEnums::DELIVERED,
    //         DeliveryStatusEnums::COMPLETED,
    //         DeliveryStatusEnums::CANCELLED,
    //     ])) {
    //         throw ValidationException::withMessages([
    //             'tracking_number' => ["Cannot assign driver. Delivery status is '{$delivery->status->value}'."]
    //         ]);
    //     }

    //     $driver = Driver::with(['user', 'transportModeDetails.partner'])->where(function ($q) use ($identifier) {
    //         $q->where('email', $identifier)
    //             ->orWhere('phone', $identifier)
    //             ->orWhere('whatsapp_number', $identifier);
    //     })->first();

    //     if (!$driver) {
    //         throw ValidationException::withMessages([
    //             'identifier' => ['No driver found with the provided identifier.']
    //         ]);
    //     }

    //     if (!in_array($driver->status, [DriverStatusEnums::ACTIVE, DriverStatusEnums::AVAILABLE])) {
    //         throw ValidationException::withMessages([
    //             'identifier' => ['Driver is not available for assignment.']
    //         ]);
    //     }


    //     // Check if driver is eligible for assignment and mode is available
    //     $this->driverService->checkDriverAndModeStatus($driver->transportModeDetails()->first()?->id ?? null);


    //     $driverChanged = $delivery->driver_id && $delivery->driver_id !== $driver->id;

    //     //Release old driver if reassigned
    //     if ($driverChanged) {
    //         $oldDriver = Driver::with('user')->find($delivery->driver_id);
    //         if ($oldDriver) {
    //             $this->notifyDriverUnassigned($oldDriver, $delivery);
    //             $oldDriver->status = DriverStatusEnums::AVAILABLE;
    //             $oldDriver->save();
    //         }
    //     }

    //     //Assign new driver
    //     $delivery->driver_id = $driver->id;
    //     $delivery->driver_assigned_at = now();

    //     //Handle partner linkage logic
    //     $transportMode = $driver->transportModeDetails()->first(); // assuming 1 driver ↔ 1 transport mode
    //     if ($transportMode && $transportMode->partner_id) {
    //         // Driver tied to a partner through a transport mode
    //         $delivery->partner_id = $transportMode->partner_id;
    //         $delivery->transport_mode_id = $transportMode->id;
    //     } elseif ($transportMode) {
    //         // Driver has a transport mode but not tied to any partner
    //         $delivery->partner_id = null;
    //         $delivery->transport_mode_id = $transportMode->id;
    //     } else {
    //         // Driver has no transport mode at all (edge case)
    //         $delivery->partner_id = null;
    //         $delivery->transport_mode_id = null;
    //     }

    //     //If delivery was queued, mark as booked
    //     if ($delivery->status === DeliveryStatusEnums::QUEUED) {
    //         $delivery->status = DeliveryStatusEnums::BOOKED;
    //     }

    //     $delivery->save();

    //     //Update driver status
    //     $driver->status = DriverStatusEnums::UNAVAILABLE;
    //     $driver->save();

    //     //Notify stakeholders
    //     $this->notifyDriverAssigned($driver, $delivery);
    //     $this->notifyCustomer($delivery, $driver, $driverChanged);

    //     return $delivery->fresh();
    // }

    protected function notifyDriverUnassigned($driver, $delivery)
    {
        $message = "Hi {$driver->name}, the LoopFreight delivery with tracking number {$delivery->tracking_number} has been reassigned.";

        try {

            //In-app notification
            $driver->notify(new DriverUnassignedFromDeliveryNotification($driver, $delivery));

            Mail::to($driver->email)->send(new DriverUnassignedFromDelivery($driver, $delivery));
            $this->termii->sendSms($driver->phone, $message);
            $this->twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
        } catch (\Throwable $e) {
            logError('Driver unassign notify failed', $e);
        }
    }

    protected function notifyDriverAssigned($driver, $delivery)
    {
        $message = "Hi {$driver->name},
        you have been assigned a LoopFreight delivery: {$delivery->tracking_number},
        Pickup Location: {$delivery->pickup_location},
        Dropoff Location: {$delivery->dropoff_location},
        Delivery Date: {$delivery->delivery_date},
        Delivery Time: {$delivery->delivery_time},
        Receiver's Name: {$delivery->receiver_name},
        Receiver's Phone Number: {$delivery->receiver_phone}.
        ";

        try {
            //In-app notification
            $driver->notify(new DriverAssignedToDeliveryNotification($driver, $delivery));

            Mail::to($driver->email)->send(new DriverAssignedToDelivery($driver, $delivery));
            $this->termii->sendSms($driver->phone, $message);
            $this->twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
        } catch (\Throwable $e) {
            logError('Driver assign notify failed', $e);
        }
    }

    protected function notifyCustomer($delivery, $driver, $driverChanged)
    {
        $customer = $delivery->customer;
        $transport = $driver->transportModeDetails()->first();
        if (!$customer || !$transport) return;

        $vehicle = ucfirst($transport->type->value) . " - {$transport->model} ({$transport->registration_number})";
        $contacts = collect([
            $driver->phone ? "Phone: {$driver->phone}" : null,
            $driver->email ? "Email: {$driver->email}" : null,
            $driver->whatsapp_number ? "WhatsApp: {$driver->whatsapp_number}" : null,
        ])->filter()->implode(', ');

        $message = "Hi {$customer->name}, your LoopFreight delivery (Tracking: {$delivery->tracking_number}) is assigned to {$driver->name}. Vehicle: {$vehicle}. Driver Contact: {$contacts}";

        try {
            $payment = $delivery->payment;

            //In-app notification
            if ($driverChanged) {
                $customer->notify(new DeliveryReassignedToCustomerNotification($delivery, $driver, $vehicle, $contacts));
            } else {
                $customer->notify(new DeliveryAssignedToCustomerNotification($delivery, $driver, $vehicle, $contacts));
            }

            if ($customer->email) {
                Mail::to($customer->email)->send(
                    $driverChanged
                        ? new DriverReassignedToUser($delivery, $driver, $transport)
                        : new DeliveryAssignedToUser($delivery, $driver, $transport, $payment)
                );
            }

            if ($customer->phone) {
                $this->termii->sendSms($customer->phone, $message);
            }

            if ($customer->whatsapp_number) {
                $this->twilio->sendWhatsAppMessage($customer->whatsapp_number, $message);
            }
        } catch (\Throwable $e) {
            logError('Customer notify failed', $e);
        }
    }
}
