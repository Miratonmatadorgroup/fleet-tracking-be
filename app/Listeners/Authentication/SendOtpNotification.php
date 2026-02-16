<?php

namespace App\Listeners\Authentication;

use App\Mail\SendOtpMail;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\Authentication\OtpRequestedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;


class SendOtpNotification
{
    use InteractsWithQueue;

    protected TermiiService $termiiService;
    protected TwilioService $twilioService;

    public function __construct(TermiiService $termiiService, TwilioService $twilioService)
    {
        $this->termiiService = $termiiService;
        $this->twilioService = $twilioService;
    }

    public function handle(OtpRequestedEvent $event): void
    {
        $channel    = $event->channel;
        $identifier = $event->identifier;
        $otp        = $event->otp;

        switch ($channel) {
            case 'phone':
                $this->termiiService->sendSms(
                    $identifier,
                    "Your LoopFreight verification code is: {$otp}"
                );
                break;

            case 'email':
                Mail::to($identifier)->send(
                    new SendOtpMail($otp, $event->name)
                );
                break;

            case 'whatsapp_number':
                $this->twilioService->sendWhatsAppMessage(
                    $identifier,
                    "Your LoopFreight verification code is: {$otp}"
                );
                break;
        }
    }
}

