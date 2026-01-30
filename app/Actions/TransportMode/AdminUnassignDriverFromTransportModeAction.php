<?php

namespace App\Actions\TransportMode;

use App\Models\Driver;
use App\Models\TransportMode;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Mail\DriverUnassignedFromTransport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\TransportMode\AdminUnassignDriverFromTransportModeDTO;
use App\Notifications\User\DriverUnassignedFromTransportNotification;

class AdminUnassignDriverFromTransportModeAction
{
    public function execute(
        AdminUnassignDriverFromTransportModeDTO $dto,
        TwilioService $twilio,
        TermiiService $termii
    ): array {
        $driver = Driver::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->first();

        if (!$driver) {
            throw new ModelNotFoundException("No driver found using the provided identifier.");
        }

        $transport = TransportMode::findOrFail($dto->transport_mode_id);

        if (is_null($transport->driver_id)) {
            throw new \Exception("No driver is currently assigned to this transport mode.", 400);
        }

        if ($transport->driver_id !== $driver->id) {
            throw new \Exception("The specified driver is not the one assigned to this transport mode.", 403);
        }

        $transport->driver_id = null;
        $transport->save();

        //In-app Notification
        $driver->notify(new DriverUnassignedFromTransportNotification($driver, $transport));

        if ($driver->email) {
            try {
                Mail::to($driver->email)->send(new DriverUnassignedFromTransport($driver, $transport));
            } catch (\Throwable $e) {
                logError("Driver unassignment email failed", $e);
            }
        }

        $message = "Hi {$driver->name}, you have been unassigned from your LoopFreight transport mode ({$transport->model}, {$transport->registration_number}).";

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

        return [
            'transport_mode' => $transport,
            'driver' => $driver,
        ];
    }
}
