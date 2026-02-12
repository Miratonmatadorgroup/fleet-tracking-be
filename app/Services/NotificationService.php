<?php

namespace App\Services;

use App\Models\User;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Delivery;
use App\Models\RewardClaim;
use App\Mail\RewardPaidMail;
use App\Models\RewardCampaign;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Models\SubscriptionPlan;
use App\Mail\RewardAvailableMail;
use Illuminate\Support\Facades\Log;
use App\Mail\DeliveryAssignedToUser;
use Illuminate\Support\Facades\Mail;
use App\Mail\GuestSubPaidMail;
use App\Mail\DeliveryAssignedToDriver;
use App\Mail\SubPaymentSuccessful;
use App\Notifications\RewardAvailableNotification;
use App\Notifications\User\DriverAssignedNotification;
use App\Notifications\User\SubscriptionPaymentNotification;
use App\Notifications\User\CustomerAssignedDriverNotification;

class NotificationService
{
    public function __construct(
        private TwilioService $twilio,
        private TermiiService $termii
    ) {}

    /**
     * Notify user payment successful
     */
    public function notifyUser(?User $userDetails, SubscriptionPlan $subPlan, ?Payment $payment): void
    {
        if (!$userDetails) {
            return;
        }

        $userName     = $userDetails->name ?? 'Customer';
        $userPhone    = $userDetails->phone ?? null;
        $userEmail    = $userDetails->email ?? null;
        $userWhatsapp = $userDetails->whatsapp_number ?? null;

        $paymentDate = $payment->created_at?->format('d M, Y • h:i A');

        // Make sure subscription relationship is loaded
        $payment->loadMissing('subscription');

        $expiryCarbon = $payment->subscription?->end_date;

        // Format for SMS/WhatsApp
        $expiryDate = $expiryCarbon?->format('d M, Y') ?? '—';

        // Convert features array to readable string
        $features = is_array($subPlan->features)
            ? implode(', ', $subPlan->features)
            : $subPlan->features;

        $amount = number_format($subPlan->price, 2);

        $msg = "Hi {$userName}, your Fleet Management subscription payment of ₦{$amount} was successful.\n\n"
            . "Plan: {$subPlan->name}\n"
            . "Billing Cycle: {$subPlan->billing_cycle->value}\n"
            . "Payment Date: {$paymentDate}\n"
            . "Expires On: {$expiryDate}\n"
            . "Access Includes: {$features}\n\n"
            . "Thank you for choosing " . config('app.name') . ".";

        // Email
        if ($userEmail) {
            $this->sendEmail($userEmail, new SubPaymentSuccessful($subPlan, $payment));
        }

        // SMS
        if ($userPhone) {
            $this->sendSmsMessage($userPhone, $msg);
        }

        // WhatsApp
        if ($userWhatsapp) {
            $this->sendWhatsapp($userWhatsapp, $msg);
        }

        // In-app notification (database)
        $userDetails->notify(
            new SubscriptionPaymentNotification(
                $subPlan,
                $userDetails,
                $payment
            )
        );
    }

    /**
     * Notify Guest Not a registered user
     */

    public function notifyGuest(User $user, SubscriptionPlan $subPlan, ?Payment $payment): void {
        $userName     = $user->name ?? 'Customer';
        $userPhone    = $user->phone;
        $userEmail    = $user->email;
        $userWhatsapp = $user->whatsapp_number;

        // Convert features array to readable text
        $featuresText = is_array($subPlan->features)
            ? implode(', ', $subPlan->features)
            : $subPlan->features;

        $amount = number_format($subPlan->price, 2);

        $msg = "Hi {$userName}, your subscription payment of ₦{$amount} was successful.\n\n"
            . "Plan: {$subPlan->name}\n"
            . "Billing Cycle: {$subPlan->billing_cycle}\n"
            . "Access Includes: {$featuresText}\n\n"
            . "Thank you for choosing " . config('app.name') . ".";

        // Email
        if ($userEmail) {
            try {
                Mail::to($userEmail)->send(
                    new GuestSubPaidMail($user, $subPlan, $payment)
                );
            } catch (\Throwable $e) {
                Log::error('Guest email failed', [
                    'to' => $userEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // SMS
        if ($userPhone) {
            try {
                $this->sendSmsMessage($userPhone, $msg);
            } catch (\Throwable $e) {
                Log::error('Guest SMS failed', [
                    'to' => $userPhone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // WhatsApp
        if ($userWhatsapp) {
            try {
                $this->sendWhatsapp($userWhatsapp, $msg);
            } catch (\Throwable $e) {
                Log::error('Guest WhatsApp failed', [
                    'to' => $userWhatsapp,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }


    /**
     * Notify Admin when no driver is available yet
     */




    /**
     * Utility: Send Email
     */
    private function sendEmail(?string $email, $mailable): void
    {
        if (!$email) return;

        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Email failed', ['error' => $e->getMessage(), 'to' => $email]);
        }
    }

    /**
     * Utility: Send SMS
     */
    private function sendSmsMessage(?string $phone, string $msg): void
    {
        if (!$phone) return;

        try {
            $this->termii->sendSms($phone, $msg);
        } catch (\Throwable $e) {
            Log::error('SMS failed', ['error' => $e->getMessage(), 'to' => $phone]);
        }
    }

    /**
     * Utility: Send WhatsApp
     */
    private function sendWhatsapp(?string $number, string $msg): void
    {
        if (!$number) return;

        try {
            $this->twilio->sendWhatsAppMessage($number, $msg);
        } catch (\Throwable $e) {
            Log::error('WhatsApp failed', ['error' => $e->getMessage(), 'to' => $number]);
        }
    }
}
