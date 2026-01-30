<?php

namespace App\Actions\TransportMode;

use App\Models\Driver;
use App\Models\TransportMode;
use App\Services\DriverService;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use Illuminate\Support\Facades\Mail;
use App\Mail\DriverAssignedToTransport;
use App\Enums\DriverApplicationStatusEnums;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\TransportMode\AdminAssignDriverToTransportModeDTO;
use App\Notifications\User\DriverAssignedToTransportNotification;

class AdminAssignDriverToTransportModeAction
{
    public function __construct(
        protected DriverService $driverService,
        protected TermiiService $termii
    ) {}
    public function execute(AdminAssignDriverToTransportModeDTO $dto, TwilioService $twilio, TermiiService $termii): TransportMode
    {
        $driver = Driver::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->first();

        if (!$driver) {
            throw new ModelNotFoundException("Driver not found using the provided identifier.");
        }

        $appStatus = $driver->application_status instanceof \BackedEnum
            ? $driver->application_status->value
            : $driver->application_status;

        $driverStatus = $driver->status instanceof \BackedEnum
            ? $driver->status->value
            : $driver->status;

        if (
            $appStatus !== DriverApplicationStatusEnums::APPROVED->value ||
            !in_array($driverStatus, [DriverStatusEnums::ACTIVE->value, DriverStatusEnums::AVAILABLE->value])
        ) {
            throw new \Exception("This driver is not active, available, or approved by an admin.");
        }

        $alreadyAssigned = TransportMode::where('driver_id', $driver->id)->first();
        if ($alreadyAssigned) {
            throw new \Exception("This driver is already assigned to a transport mode (ID: {$alreadyAssigned->id}), kindly unassign the driver first.");
        }

        $transport = TransportMode::findOrFail($dto->transport_mode_id);

        if (!is_null($transport->driver_id)) {
            throw new \Exception("This transport mode has already been assigned to a driver.");
        }

        $driverTransportMode = $driver->transport_mode instanceof \BackedEnum
            ? $driver->transport_mode->value
            : $driver->transport_mode;

        $transportModeType = $transport->type instanceof \BackedEnum
            ? $transport->type->value
            : $transport->type;

        if ($driverTransportMode !== $transportModeType) {
            throw new \Exception(
                "Driver's registered transport mode ({$driverTransportMode}) does not match the assigned transport mode ({$transportModeType})."
            );
        }


        $transport->driver_id = $driver->id;
        $transport->save();

        // Reload transport with driver relationship (important for validation)
        $transport = TransportMode::with(['driver:id,id,is_flagged,flag_reason'])
            ->findOrFail($transport->id);

        // Validate driver + transport mode availability
        $this->driverService->checkDriverAndModeStatus($transport);

        // Normalize mode value if needed later
        $modeOfTransport = $transport->type instanceof \BackedEnum
            ? $transport->type->value
            : (string) $transport->type;

        // Validate that driver + transport mode is eligible

        // In-app notification
        if ($driver->user) {
            $driver->user->notify(new DriverAssignedToTransportNotification($driver, $transport));
        }

        if ($driver->email) {
            try {
                Mail::to($driver->email)->send(new DriverAssignedToTransport($driver, $transport));
            } catch (\Throwable $e) {
                logError("Driver assignment email failed", $e);
            }
        }

        $message = "Hi {$driver->name}, you have been assigned to a LoopFreight {$transport->mode}, LoopFreight transport mode (ID: {$transport->id}).";

        if ($driver->phone) {
            try {
                $termii->sendSms($driver->phone, $message);
            } catch (\Throwable $e) {
                logError("Termii SMS failed", $e);
            }

            try {
                $twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
            } catch (\Throwable $e) {
                logError("Twilio WhatsApp failed", $e);
            }
        }

        return $transport->load('driver');
    }
}
