<?php

namespace App\Actions\Driver;

use App\Models\Driver;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use Illuminate\Support\Facades\Mail;
use App\Mail\DriverApplicationDecision;
use App\Enums\DriverApplicationStatusEnums;
use Illuminate\Validation\ValidationException;
use App\DTOs\Driver\AdminApproveOrRejectDriverDTO;
use App\Notifications\User\DriverApplicationDecisionNotification;

class AdminApproveOrRejectDriverAction
{
    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii,
    ) {}

    public function execute(AdminApproveOrRejectDriverDTO $dto): Driver
    {
        $driver = Driver::with('user')->findOrFail($dto->driverId);

        // Prevent re-approval or re-rejection
        if ($driver->application_status === DriverApplicationStatusEnums::APPROVED) {
            throw ValidationException::withMessages([
                'action' => ['This driver has already been approved and cannot be rejected.']
            ]);
        }

        if ($driver->application_status === DriverApplicationStatusEnums::REJECTED) {
            throw ValidationException::withMessages([
                'action' => ['This driver has already been rejected and cannot be approved.']
            ]);
        }

        $action = $dto->action;

        if ($action === 'approve') {
            $driver->application_status = DriverApplicationStatusEnums::APPROVED;
            $driver->status = DriverStatusEnums::ACTIVE;
            $driver->user?->assignRole('driver');
        } elseif ($action === 'reject') {
            $driver->application_status = DriverApplicationStatusEnums::REJECTED;
            $driver->status = DriverStatusEnums::INACTIVE;
        }

        $driver->save();

        $message = match ($action) {
            'approve' => "Hi {$driver->name}, your LoopFreight driver application has been approved. Welcome aboard!",
            'reject' => "Hi {$driver->name}, unfortunately, your LoopFreight driver application has been rejected. Please contact support for more information.",
        };

        // in-app Notificaton
        if ($driver->user) {
            $driver->user->notify(new DriverApplicationDecisionNotification($driver, $action));
        }

        //Email
        if ($driver->email) {
            try {
                Mail::to($driver->email)->send(new DriverApplicationDecision($driver, $action));
            } catch (\Throwable $e) {
                logError('Email send failed', $e);
            }
        }
        //SMS
        if ($driver->phone) {
            try {
                $this->termii->sendSms($driver->phone, $message);
            } catch (\Throwable $e) {
                logError('SMS failed', $e);
            }
        }
        //WhatsApp
        if ($driver->whatsapp_number) {
            try {
                $this->twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
            } catch (\Throwable $e) {
                logError('WhatsApp failed', $e);
            }
        }

        return $driver->refresh();
    }
}
