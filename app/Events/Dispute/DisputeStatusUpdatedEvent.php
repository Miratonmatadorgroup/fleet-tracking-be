<?php

namespace App\Events\Dispute;

use App\Models\Dispute;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DisputeStatusEnums;
use App\Mail\DisputeDecisionMail;
use Illuminate\Support\Facades\Mail;
use App\Notifications\User\DisputeStatusUpdatedNotification;

class DisputeStatusUpdatedEvent
{
    public function __construct(
        public readonly Dispute $dispute,
        public readonly string $status,
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {
        $this->handle();
    }

    protected function handle(): void
    {
        $user = $this->dispute->user;

        if (in_array($this->status, DisputeStatusEnums::values(), true)) {
            Mail::to($user->email)->send(new DisputeDecisionMail($this->dispute, $user->name));

            // Send In-App Notification
            $user->notify(new DisputeStatusUpdatedNotification(
                DisputeStatusEnums::from($this->status),
                $this->dispute->title
            ));
        }

        // Send SMS
        if ($user->phone) {
            $message = "Hi {$user->name}, your LoopFreight dispute status has been updated to \"{$this->status}\".";
            $this->termii->sendSms($user->phone, $message);
        }

        // Send WhatsApp
        if ($user->whatsapp_number) {
            $message = "Hello {$user->name}, your LoopFreight dispute titled \"{$this->dispute->title}\" is now marked as \"{$this->status}\".";
            $this->twilio->sendWhatsAppMessage($user->whatsapp_number, $message);
        }
    }
}
