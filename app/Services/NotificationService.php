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
use App\Mail\RewardAvailableMail;
use Illuminate\Support\Facades\Log;
use App\Mail\DeliveryAssignedToUser;
use Illuminate\Support\Facades\Mail;
use App\Mail\GuestDeliveryBookedMail;
use App\Mail\DeliveryAssignedToDriver;
use App\Mail\PaymentSuccessButNoDriverYet;
use App\Notifications\RewardAvailableNotification;
use App\Notifications\User\DriverAssignedNotification;
use App\Notifications\User\CustomerNoDriverYetNotification;
use App\Notifications\User\CustomerAssignedDriverNotification;

class NotificationService
{
    public function __construct(
        private TwilioService $twilio,
        private TermiiService $termii
    ) {}

    /**
     * Notify driver when assigned a delivery
     */
    public function notifyDriver(?Driver $driver, Delivery $delivery): void
    {
        if (!$driver) {
            return; // Nothing to notify
        }

        $customer = $delivery->customer;

        $customerName = $customer->name ?? 'Customer';
        $customerContact = $customer->phone ?? $customer->email ?? $customer->whatsapp_number ?? 'N/A';
        $receiverName = $delivery->receiver_name ?? 'Receiver';
        $receiverContact = $delivery->receiver_phone ?? 'N/A';

        $driverName = $driver->name ?? 'N/A';
        $driverPhone = $driver->phone ?? 'N/A';
        $driverWhatsapp = $driver->whatsapp_number ?? 'N/A';

        $msg = "Hi {$driverName}, you have a new LoopFreight delivery for {$delivery->delivery_date} at {$delivery->delivery_time}.
        From: {$delivery->pickup_location} to {$delivery->dropoff_location}.

        Customer: {$customerName}
        Contact: {$customerContact}

       Receiver: {$receiverName}
       Receiver Contact: {$receiverContact}";

        // Email
        // if ($driver?->email && $driver?->email_verified_at) {
        //     $this->sendEmail(
        //         $customer->email,
        //         new DeliveryAssignedToDriver($delivery, $driver)
        //     );
        // }

        $this->sendEmail($driver->email, new DeliveryAssignedToDriver($delivery, $driver));

        // SMS
        $this->sendSmsMessage($driver->phone, $msg);

        // WhatsApp
        $this->sendWhatsapp($driver->whatsapp_number, $msg);

        $driver->notify(new DriverAssignedNotification($delivery));
    }

    /**
     * Notify customer when payment successful and driver assigned
     */
    public function notifyCustomerWithDriver(?User $customer, Delivery $delivery, ?Driver $driver, ?Payment $payment): void
    {
        $customerName = $customer->name ?? 'Customer';
        $customerPhone = $customer->phone ?? $delivery->sender_phone ?? $delivery->receiver_phone ?? 'N/A';
        $customerEmail = $customer->email ?? $delivery->sender_email ?? $delivery->receiver_email ?? null;
        $customerWhatsapp = $customer->whatsapp_number ?? $delivery->sender_whatsapp_number ?? $delivery->receiver_whatsapp_number ?? null;

        $driverName = $driver->name ?? 'N/A';
        $driverPhone = $driver->phone ?? 'N/A';
        $driverWhatsapp = $driver->whatsapp_number ?? 'N/A';

        $msg = "Hi {$customerName}, your LoopFreight payment of ₦" . number_format($delivery->total_price, 2) . " was successful.
    Tracking No: {$delivery->tracking_number}.
    Waybill No: {$delivery->waybill_number}.
    Driver: {$driverName}, Phone: {$driverPhone}, WhatsApp: {$driverWhatsapp}.";

        $transport = $delivery->transport_mode ?? $driver?->transportModeDetails;

        // Email
        // if ($customer?->email && $customer?->email_verified_at) {
        //     $this->sendEmail(
        //         $customer->email,
        //         new DeliveryAssignedToUser($delivery, $driver, $transport, $payment)
        //     );
        // }

        $this->sendEmail($customerEmail, new DeliveryAssignedToUser($delivery, $driver, $transport, $payment));

        // SMS
        $this->sendSmsMessage($customerPhone, $msg);

        // WhatsApp
        $this->sendWhatsapp($customerWhatsapp, $msg);

        if ($customer) {
            $customer->notify(new CustomerAssignedDriverNotification($delivery, $driver));
        }
    }

    /**
     * Notify customer when no driver is available yet
     */
    public function notifyCustomerNoDriver(?User $customer, Delivery $delivery, ?Payment $payment): void
    {
        $customerName = $customer->name ?? 'Customer';
        $customerPhone = $customer->phone ?? $delivery->sender_phone ?? $delivery->receiver_phone ?? 'N/A';
        $customerEmail = $customer->email ?? $delivery->sender_email ?? $delivery->receiver_email ?? null;
        $customerWhatsapp = $customer->whatsapp_number ?? $delivery->sender_whatsapp_number ?? $delivery->receiver_whatsapp_number ?? null;

        $msg = "Hi {$customerName}, your LoopFreight payment of ₦" . number_format($delivery->total_price, 2) . " was successful.
        Tracking No: {$delivery->tracking_number}.
        Waybill No: {$delivery->waybill_number}.
        We will assign a driver shortly and notify you.";

        // Email
        // if ($customer?->email && $customer?->email_verified_at) {
        //     $this->sendEmail(
        //         $customer->email,
        //         new PaymentSuccessButNoDriverYet($delivery, $payment)
        //     );
        // }

        $this->sendEmail($customerEmail, new PaymentSuccessButNoDriverYet($delivery, $payment));

        // SMS
        $this->sendSmsMessage($customerPhone, $msg);

        // WhatsApp
        $this->sendWhatsapp($customerWhatsapp, $msg);

        if ($customer) {
            $customer->notify(new CustomerNoDriverYetNotification($delivery));
        }
    }
    /**
     * Notify Guest Not a registered user
     */

    public function notifyGuest(?string $phone, ?string $email, ?string $whatsapp_number, string $msg): void
    {
        // Email
        if ($email) {
            try {
                Mail::to($email)->send(new GuestDeliveryBookedMail($msg));
            } catch (\Throwable $e) {
                Log::error('Guest email failed', ['to' => $email, 'error' => $e->getMessage()]);
            }
        }
        // SMS
        if ($phone) {
            try {
                $this->sendSmsMessage($phone, $msg);
            } catch (\Throwable $e) {
                Log::error('Guest SMS failed', [
                    'to' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // WhatsApp
        if ($whatsapp_number) {
            try {
                $this->sendWhatsapp($whatsapp_number, $msg);
            } catch (\Throwable $e) {
                Log::error('Guest WhatsApp failed', [
                    'to' => $whatsapp_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notify Admin when no driver is available yet
     */
    public function notifyAdminsNoDriver(Delivery $delivery): void
    {
        try {
            $admins = User::role(['admin', 'customer_care'])->get();
            \Illuminate\Support\Facades\Notification::send(
                $admins,
                new \App\Notifications\Admin\NoAvailableDriverNotification($delivery)
            );
        } catch (\Throwable $e) {
            Log::error('Admin no-driver notification failed', [
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id
            ]);
        }
    }

    public function notifyRewardAvailable(User $driver, RewardCampaign $campaign): void
    {
        try {
            // Message for SMS and WhatsApp
            $msg = "Hi {$driver->name}, you’ve unlocked LoopFreight reward: ₦{$campaign->reward_amount} from the '{$campaign->name}' campaign! Login to your dashboard to claim.";

            // Email
            $this->sendEmail($driver->email, new RewardAvailableMail($driver, $campaign));

            // SMS
            $this->sendSmsMessage($driver->phone, $msg);

            // WhatsApp
            $this->sendWhatsapp($driver->whatsapp_number, $msg);

            // In-app notification
            $driver->notify(new RewardAvailableNotification($campaign));
        } catch (\Throwable $e) {
            Log::error("Failed to notify driver of reward: " . $e->getMessage());
        }
    }

    public function notifyRewardPaid(User $driver, RewardClaim $claim): void
    {
        $msg = "Well done {$driver->name}. Your LoopFreight reward of ₦" . number_format($claim->amount, 2) . " has been credited to your wallet.";

        $this->sendEmail($driver->email, new RewardPaidMail($driver, $claim));
        $this->sendSmsMessage($driver->phone, $msg);
        $this->sendWhatsapp($driver->whatsapp_number, $msg);

        $driver->notify(new \App\Notifications\RewardPaidNotification($claim));
    }



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
