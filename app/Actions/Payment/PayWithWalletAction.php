<?php

namespace App\Actions\Payment;


use App\Models\Driver;
use App\Models\Payment;
use App\Models\Delivery;
use Illuminate\Support\Str;
use App\Models\TransportMode;
use App\Services\DriverService;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Enums\PaymentStatusEnums;
use App\Models\WalletTransaction;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\DeliveryAssignedToUser;
use App\Services\WalletGuardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\ExternalBankService;
use App\Services\NotificationService;
use App\DTOs\Payment\PayWithWalletDTO;
use App\Mail\DeliveryAssignedToDriver;
use App\Services\TransactionPinService;
use App\Services\WalletPurchaseService;
use App\Mail\PaymentSuccessButNoDriverYet;
use App\Enums\WalletTransactionStatusEnums;


class PayWithWalletAction
{
    public function __construct(
        private DriverService $driverService,
        private NotificationService $notificationService
    ) {}

    public function execute(PayWithWalletDTO $dto): Delivery
    {
        $user = Auth::user();

        $delivery = Delivery::where('id', $dto->delivery_id)
            ->where('customer_id', $user->id)
            ->with('customer', 'payment')
            ->firstOrFail();

        if ($delivery->status !== DeliveryStatusEnums::PENDING_PAYMENT) {
            throw new \Exception(
                'This delivery has already been paid for or is not payable.'
            );
        }

        //PIN validation
        app(TransactionPinService::class)->checkPin($user, $dto->pin);

        $wallet = $user->wallet;

        /** @var WalletGuardService $guard */
        $guard = app(WalletGuardService::class);

        /** @var ExternalBankService $bank */
        $bank = app(ExternalBankService::class);

        //Wallet & Shanono guards
        $guard->ensureCanSpend($user, $delivery->total_price);
        $guard->ensureExternalAccountActive($wallet, $bank);
        $guard->ensureMerchantLiquidity($bank, $delivery->total_price);

        /** @var WalletPurchaseService $walletPurchase */
        $walletPurchase = app(WalletPurchaseService::class);

        //Wallet debit (atomic & safe)
        $walletTransaction = $walletPurchase->process(
            user: $user,
            wallet: $wallet,
            amount: $delivery->total_price,
            method: 'wallet',
            providerCallback: fn(string $reference) => [
                [
                    'responseCode' => 200,
                    'reference'    => $reference,
                ],
            ],
            meta: [
                'purpose'     => 'delivery_payment',
                'delivery_id' => $delivery->id,
            ]
        );

        //Post-payment persistence
        DB::transaction(function () use ($delivery, $walletTransaction) {
            $delivery->update([
                'tracking_number' => $this->generateTrackingNumber(),
                'waybill_number'  => $this->generateWaybillNumber(),
                'status'          => DeliveryStatusEnums::BOOKED,
            ]);

            Payment::where('delivery_id', $delivery->id)->update([
                'status'    => PaymentStatusEnums::PAID->value,
                'gateway'   => 'wallet',
                'reference' => $walletTransaction->reference,
            ]);
        });

        // Refresh payment reference
        $payment = $delivery->fresh()->payment;

        //Driver assignment + notifications
        $driver = $this->driverService->findNearestAvailable($delivery);

        if ($driver) {
            $this->notificationService
                ->notifyDriver($driver, $delivery);

            $this->notificationService
                ->notifyCustomerWithDriver(
                    $delivery->customer,
                    $delivery,
                    $driver,
                    $payment
                );
        } else {
            $this->notificationService
                ->notifyCustomerNoDriver(
                    $delivery->customer,
                    $delivery,
                    $payment
                );

            $this->notificationService
                ->notifyAdminsNoDriver($delivery);
        }

        return $delivery->fresh();
    }

    public function adminExecute(string $deliveryId, ?Driver $driver = null, ?string $pin = null): Delivery
    {
        $admin = Auth::user();

        // Check roles
        if (!$admin->hasAnyRole(['admin', 'customer_care'])) {
            throw new \Exception('You are not authorized to perform this action.');
        }

        // PIN validation
        app(TransactionPinService::class)->checkPin($admin, $pin);

        // Fetch delivery
        $delivery = Delivery::where('id', $deliveryId)
            ->with('customer', 'payment')
            ->firstOrFail();

        // Idempotency check: only proceed if pending payment
        if ($delivery->status !== DeliveryStatusEnums::PENDING_PAYMENT) {
            throw new \Exception('This delivery has already been paid for.');
        }

        $wallet = $admin->wallet;
        if (!$wallet) {
            throw new \Exception('Admin wallet not found.');
        }

        /** @var WalletGuardService $guard */
        $guard = app(WalletGuardService::class);

        /** @var ExternalBankService $bank */
        $bank = app(ExternalBankService::class);

        // Ensure admin wallet has enough balance
        $guard->ensureCanSpend($admin, $delivery->total_price);
        $guard->ensureExternalAccountActive($wallet, $bank);
        $guard->ensureMerchantLiquidity($bank, $delivery->total_price);

        DB::beginTransaction();
        try {
            // Deduct from admin wallet
            $wallet->available_balance -= $delivery->total_price;
            $wallet->total_balance -= $delivery->total_price;
            $wallet->save();

            // Record wallet transaction
            $walletTransaction = WalletTransaction::create([
                'id' => Str::uuid(),
                'wallet_id' => $wallet->id,
                'user_id' => $admin->id,
                'type' => 'debit',
                'amount' => $delivery->total_price,
                'description' => "Admin payment for delivery {$delivery->id}",
                'reference' => 'WALTX-' . strtoupper(Str::random(10)),
                'status' => WalletTransactionStatusEnums::SUCCESS->value,
                'method' => 'wallet',
            ]);

            // Update delivery
            $delivery->update([
                'tracking_number' => $this->generateTrackingNumber(),
                'waybill_number'  => $this->generateWaybillNumber(),
                'status'          => DeliveryStatusEnums::BOOKED,
            ]);

            // Update payment record
            $payment = Payment::where('delivery_id', $delivery->id)->first();
            if ($payment) {
                $payment->update([
                    'status'    => PaymentStatusEnums::PAID->value,
                    'gateway'   => 'wallet',
                    'reference' => $walletTransaction->reference,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Admin wallet payment failed', [
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id
            ]);
            throw $e; // Let caller know
        }

        try {
            if ($driver) {
                $this->notificationService->notifyDriver($driver, $delivery);
                $this->notificationService->notifyCustomerWithDriver(
                    $delivery->customer,
                    $delivery,
                    $driver,
                    $payment
                );
            } else {
                $this->notificationService->notifyCustomerNoDriver(
                    $delivery->customer,
                    $delivery,
                    $payment
                );
                $this->notificationService->notifyAdminsNoDriver($delivery);
            }

            // Guest fallback notifications
            $customer = $delivery->customer;
            $phone = $customer->phone ?? $delivery->sender_phone ?? $delivery->receiver_phone ?? null;
            $email = $customer->email ?? $delivery->sender_email ?? $delivery->receiver_email ?? null;
            $whatsapp = $customer->whatsapp_number ?? $delivery->sender_whatsapp_number ?? $delivery->receiver_whatsapp_number ?? null;

            $driverName = $driver->name ?? 'N/A';
            $driverPhone = $driver->phone ?? 'N/A';
            $driverWhatsapp = $driver->whatsapp_number ?? 'N/A';

            $message = "Your LoopFreight delivery payment of ₦" . number_format($delivery->total_price, 2) . " has been successfully paid by admin.\n"
                . "Tracking Number: {$delivery->tracking_number}\n"
                . "Waybill Number: {$delivery->waybill_number}\n"
                . "Sender Name: {$delivery->sender_name}\n"
                . "Receiver Name: {$delivery->receiver_name}\n"
                . ($driver
                    ? "Driver Contact: {$driverName}, Phone: {$driverPhone}, WhatsApp: {$driverWhatsapp}."
                    : "A driver will be assigned to your delivery shortly.");

            $this->notificationService->notifyGuest($phone, $email, $whatsapp, $message);
        } catch (\Throwable $e) {
            Log::error('Notification failed', [
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id
            ]);
        }

        return $delivery->fresh();
    }

    protected function generateTrackingNumber(): string
    {
        return 'LPF-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }

    protected function generateWaybillNumber(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';

        $code = $letters[random_int(0, strlen($letters) - 1)] .
            $numbers[random_int(0, strlen($numbers) - 1)];

        $pool = $letters . $numbers;
        for ($i = 0; $i < 3; $i++) {
            $code .= $pool[random_int(0, strlen($pool) - 1)];
        }

        $code = str_shuffle($code);

        return 'WB-' . $code;
    }
}
