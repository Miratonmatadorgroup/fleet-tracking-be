<?php

namespace App\Actions\Partner;

use App\Models\Driver;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\DriverApplicationDecision;
use App\Enums\DriverApplicationStatusEnums;
use Illuminate\Validation\ValidationException;
use App\Events\Partner\FleetApplicationApproved;
use App\DTOs\Driver\AdminApproveOrRejectDriverDTO;
use App\Notifications\User\DriverApplicationDecisionNotification;


class FleetApplicationDecisionAction
{
    public function __construct(
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {}

    public function execute(AdminApproveOrRejectDriverDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $driver = Driver::with([
                'transportModeDetails',
                'user',
                'transportModeDetails.partner.user'
            ])->findOrFail($dto->driverId);

            if (
                ($driver->application_status === DriverApplicationStatusEnums::APPROVED && $dto->action === 'reject') ||
                ($driver->application_status === DriverApplicationStatusEnums::REJECTED && $dto->action === 'approve')
            ) {
                throw ValidationException::withMessages([
                    'action' => ["You cannot {$dto->action} a fleet that has already been {$driver->application_status->value}."]
                ]);
            }

            $transport = $driver->transportModeDetails;

            if ($dto->action === 'approve') {
                $driver->update([
                    'status' => DriverStatusEnums::ACTIVE,
                    'application_status' => DriverApplicationStatusEnums::APPROVED,
                ]);

                if ($transport) {
                    $transport->update(['approval_status' => 'approved']);
                }

                if ($driver->user && !$driver->user->hasRole('driver')) {
                    $driver->user->assignRole('driver');
                }

                //Send notifications
                $this->sendNotifications($driver, 'approve');

                FleetApplicationApproved::dispatch($driver, $transport);
            } else {
                $driver->update([
                    'status' => DriverStatusEnums::INACTIVE,
                    'application_status' => DriverApplicationStatusEnums::REJECTED,
                ]);

                if ($transport) {
                    $transport->update(['approval_status' => 'rejected']);
                }

                $this->sendNotifications($driver, 'reject');
            }

            return [
                'driver' => $driver->fresh(),
                'transport' => $transport ? $transport->fresh() : null,
            ];
        });
    }

    protected function sendNotifications(Driver $driver, string $action): void
    {
        $message = match ($action) {
            'approve' => "Hi {$driver->name}, your LoopFreight driver application has been approved. Welcome onboard!",
            'reject' => "Hi {$driver->name}, unfortunately, your LoopFreight driver application has been rejected.",
        };

        //in-app Notification
        if ($driver->user) {
            $driver->user->notify(new DriverApplicationDecisionNotification($driver, $action));
        }

        //DRIVER NOTIFICATIONS
        if ($driver->email) {
            try {
                Mail::to($driver->email)->send(new DriverApplicationDecision($driver, $action));
            } catch (\Throwable $e) {
                logError('Driver email send failed', $e);
            }
        }

        if ($driver->phone) {
            try {
                $this->termii->sendSms($driver->phone, $message);
            } catch (\Throwable $e) {
                logError('Driver SMS failed', $e);
            }
        }

        if ($driver->whatsapp_number) {
            try {
                $this->twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
            } catch (\Throwable $e) {
                logError('Driver WhatsApp failed', $e);
            }
        }

        //PARTNER NOTIFICATIONS
        $partnerUser = $driver->transportModeDetails?->partner?->user;

        if ($partnerUser) {
            $partnerMessage = match ($action) {
                'approve' => "Hi {$partnerUser->name}, your LoopFreight assigned driver {$driver->name}'s application has been approved.",
                'reject' => "Hi {$partnerUser->name}, your LoopFreight assigned driver {$driver->name}'s application has been rejected.",
            };

            //In-app Notification
            $partnerUser->notify(new \App\Notifications\User\FleetApplicationDecisionNotification($driver, $action));

            //Email to partner
            if ($partnerUser->email) {
                try {
                    Mail::to($partnerUser->email)->send(new \App\Mail\FleetApplicationDecision($driver, $action));
                } catch (\Throwable $e) {
                    logError('Partner email send failed', $e);
                }
            }

            //SMS to partner
            if ($partnerUser->phone) {
                try {
                    $this->termii->sendSms($partnerUser->phone, $partnerMessage);
                } catch (\Throwable $e) {
                    logError('Partner SMS failed', $e);
                }
            }

            //WhatsApp to partner
            if ($partnerUser->whatsapp_number) {
                try {
                    $this->twilio->sendWhatsAppMessage($partnerUser->whatsapp_number, $partnerMessage);
                } catch (\Throwable $e) {
                    logError('Partner WhatsApp failed', $e);
                }
            }
        }
    }
}
