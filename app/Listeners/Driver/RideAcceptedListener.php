<?php

namespace App\Listeners\Driver;

use App\Mail\RideAcceptedEmail;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Mail;
use App\Events\Driver\RideAcceptedEvent;
use App\Notifications\User\RideAcceptedNotification;

class RideAcceptedListener
{
    public function handle(RideAcceptedEvent $event)
    {
        $ride = $event->ride;
        $user = $ride->user;
        $driver = $event->driver;

        // In-app notification
        $user->notify(new RideAcceptedNotification($ride, $driver));

        // Email
        if ($user->email) {
            Mail::to($user->email)->send(new RideAcceptedEmail($user, $ride, $driver));
        }


        $transportType = ucfirst($ride->transportMode?->type?->value ?? 'N/A');

        // SMS
        if ($user->phone) {

            $smsMessage =
                "Your LoopFreight ride has been accepted by a driver, and is now heading to your pickup location!\n" .
                "Driver: {$driver->name} ({$driver->phone})\n" .
                "Ref: {$ride->id}\n" .
                "Status: In Transit\n" .
                "Vehicle: {$transportType}\n" .
                "Model: {$ride->transportMode?->manufacturer} {$ride->transportMode?->model}\n" .
                "Plate: {$ride->transportMode?->registration_number}\n";

            app(TermiiService::class)->sendSms($user->phone, $smsMessage);
        }

        // WhatsApp
        if ($user->whatsapp_number) {

            $whatsappMessage =
                "*Your LoopFreight ride has been accepted by a driver, and is now heading to your pickup location!!*\n\n" .
                "*Driver:* {$driver->name} ({$driver->phone})\n" .
                "*Ref:* {$ride->id}\n" .
                "*Status:* In Transit\n\n" .
                "*Transport Mode Details:*\n" .
                "• Type: {$transportType}\n" .
                "• Manufacturer: {$ride->transportMode?->manufacturer}\n" .
                "• Model: {$ride->transportMode?->model}\n" .
                "• Color: {$ride->transportMode?->color}\n" .
                "• Plate: {$ride->transportMode?->registration_number}\n" .
                "• Capacity: {$ride->transportMode?->passenger_capacity}\n\n" .
                "The driver is now heading to your pickup location.";

            app(TwilioService::class)->sendWhatsAppMessage($user->whatsapp_number, $whatsappMessage);
        }
    }
}
