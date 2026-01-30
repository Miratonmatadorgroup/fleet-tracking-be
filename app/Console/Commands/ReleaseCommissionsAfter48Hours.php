<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Dispute;
use App\Models\Delivery;
use App\Services\TwilioService;
use App\Services\WalletService;
use Illuminate\Console\Command;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Notifications\User\DeliveryCompletedInAppNotification;
use App\Services\TermiiService;

class ReleaseCommissionsAfter48Hours extends Command
{
    protected $signature = 'commissions:release-pending';
    protected $description = 'Release commissions for deliveries not confirmed after 48 hours and no dispute filed.';

    public function handle()
    {
        $cutoff = Carbon::now()->subHours(48);
        $twilio = app(TwilioService::class);
        $termii = app(TermiiService::class);

        // Get deliveries that were marked as DELIVERED but not yet COMPLETED
        $deliveries = Delivery::where('status', DeliveryStatusEnums::DELIVERED)
            ->where('updated_at', '<=', $cutoff) // Assuming updated_at is when it was marked as DELIVERED
            ->whereNotNull('tracking_number')
            ->with(['customer', 'driver.user', 'partner', 'investor', 'platform'])
            ->get();

        if ($deliveries->isEmpty()) {
            $this->info("No eligible deliveries found.");
            return;
        }

        foreach ($deliveries as $delivery) {
            $trackingNumber = $delivery->tracking_number;

            $hasDispute = Dispute::where('tracking_number', $trackingNumber)->exists();

            if (!$hasDispute) {
                $driverUser = $delivery->driver?->user;

                // Mark as completed
                $delivery->status = DeliveryStatusEnums::COMPLETED;
                $delivery->save();

                // Credit commissions
                $customer = $delivery->customer;
                $trackingNumber = $delivery->tracking_number;

                if ($driverUser) {
                    WalletService::creditCommissions($driverUser, $delivery);
                    $driverUser->notify(new DeliveryCompletedInAppNotification($delivery, 'driver'));
                }

                if ($customer) {
                    $customer->notify(new DeliveryCompletedInAppNotification($delivery, 'customer'));
                }

                $message = "LoopFreight Delivery (Tracking No: {$trackingNumber}) has been confirmed completed.";

                try {
                    // Email
                    if ($customer?->email) {
                        Mail::to($customer->email)->send(new \App\Mail\DeliveryCompletedNotification($delivery, 'customer'));
                    }

                    if ($driverUser?->email) {
                        Mail::to($driverUser->email)->send(new \App\Mail\DeliveryCompletedNotification($delivery, 'driver'));
                    }

                    // SMS
                    if ($driverUser?->phone) {
                        $termii->sendSms($driverUser->phone, $message);
                    }

                    if ($customer?->phone) {
                        $termii->sendSms($customer->phone, $message);
                    }

                    // WhatsApp
                    if ($driverUser?->whatsapp_number) {
                        $twilio->sendWhatsAppMessage($driverUser->whatsapp_number, $message);
                    }

                    if ($customer?->whatsapp_number) {
                        $twilio->sendWhatsAppMessage($customer->whatsapp_number, $message);
                    }

                    $this->info("Notifications sent for delivery: {$trackingNumber}");
                } catch (\Throwable $e) {
                    Log::error("Notification error for delivery {$trackingNumber}: {$e->getMessage()}");
                    $this->warn("Notification failed for delivery: {$trackingNumber}");
                }
            } else {
                $this->info("Dispute found. Skipping delivery: {$trackingNumber}");
            }
        }

        $this->info("Commission release job completed.");
    }
}
