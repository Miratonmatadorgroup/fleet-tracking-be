<?php

namespace App\Actions\Delivery;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Payment;
use App\Models\Delivery;
use App\Models\Discount;
use App\Models\TransportMode;
use App\Services\DriverService;
use App\Services\PricingService;
use App\Enums\PaymentStatusEnums;
use App\Enums\TransportModeEnums;
use App\Models\WalletTransaction;
use App\Services\DiscountService;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Log;
use App\Services\WalletGuardService;
use App\Services\DeliveryTimeService;
use App\Services\ExternalBankService;
use App\Services\NotificationService;
use App\Services\TransactionPinService;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\DTOs\Delivery\ExternalBookDeliveryDTO;

class ExternalBookDeliveryAction
{
    protected const EXTERNAL_REF_PREFIX = 'EXTERNAL-';

    protected PricingService $pricingService;
    protected DeliveryTimeService $deliveryTimeService;
    protected DriverService $driverService;
    protected NotificationService $notificationService;

    protected DiscountService $discountService;

    protected WalletGuardService $walletGuard;
    protected ExternalBankService $externalBank;


    public function __construct(
        PricingService $pricingService,
        DeliveryTimeService $deliveryTimeService,
        DriverService $driverService,
        NotificationService $notificationService,
        DiscountService $discountService,
        WalletGuardService $walletGuard,
        ExternalBankService $externalBank

    ) {
        $this->pricingService = $pricingService;
        $this->deliveryTimeService = $deliveryTimeService;
        $this->driverService = $driverService;
        $this->notificationService = $notificationService;
        $this->discountService = $discountService;
        $this->walletGuard = $walletGuard;
        $this->externalBank = $externalBank;
    }

    /**
     * Execute booking and create both Delivery + Payment
     *
     * @return array{delivery:Delivery, payment:Payment, message:?string}
     */
    public function execute(ExternalBookDeliveryDTO $dto): array
    {

        $today = now()->startOfDay();
        $deliveryDate = \Carbon\Carbon::parse($dto->deliveryDate)->startOfDay();

        if ($deliveryDate->lt($today)) {
            throw new \InvalidArgumentException(
                "Delivery date not accepted. Please select today or a future date."
            );
        }
        $modeEnum = TransportModeEnums::tryFrom($dto->modeOfTransportation);
        if (!$modeEnum) {
            throw new \InvalidArgumentException("Invalid transport mode: {$dto->modeOfTransportation}");
        }

        $deliveryPicsPaths = $dto->deliveryPictures ?? [];
        $coordinates = $this->getCoordinates($dto, $modeEnum);

        $pickupInfo = $this->reverseGeocode(
            $coordinates['pickup_latitude'],
            $coordinates['pickup_longitude']
        );

        $dropoffInfo = $this->reverseGeocode(
            $coordinates['dropoff_latitude'],
            $coordinates['dropoff_longitude']
        );

        //Block deliveries outside Lagos
        if ($pickupInfo && isset($pickupInfo['state'])) {
            if (strtolower($pickupInfo['state']) !== 'lagos') {
                throw new \Exception("Delivery is not available for now outside Lagos.", 422);
            }
        }

        [$transportMode, $deliveryNotice] = $this->resolveTransportMode(
            $dto,
            $pickupInfo,
            $dropoffInfo,
            $coordinates,
            $modeEnum
        );

        if ($transportMode instanceof TransportModeEnums) {
            $transportMode = TransportMode::where('type', $transportMode->value)->firstOrFail();
        } elseif (is_int($transportMode)) {
            $transportMode = TransportMode::findOrFail($transportMode);
        } elseif (is_string($transportMode)) {
            $transportMode = TransportMode::where('type', $transportMode)->firstOrFail();
        }

        if (!$transportMode instanceof TransportMode) {
            throw new \LogicException(
                'resolveTransportMode() returned invalid type: ' .
                    (is_object($transportMode)
                        ? get_class($transportMode)
                        : gettype($transportMode))
            );
        }


        $transportMode->loadMissing(['driver:id,id,is_flagged,flag_reason']);

        $modeValue = $transportMode->type instanceof \BackedEnum
            ? $transportMode->type->value
            : (string) $transportMode->type;

        Log::error('TRANSPORT MODE DEBUG', [
            'value' => $transportMode,
            'type'  => is_object($transportMode)
                ? get_class($transportMode)
                : gettype($transportMode),
        ]);


        $this->driverService->checkDriverAndModeStatus($transportMode);
        // $pricing = $this->calculatePricing($dto, $coordinates, $pickupInfo, $dropoffInfo, $transportMode);
        $pricing = $this->calculatePricing(
            $dto,
            $coordinates,
            $pickupInfo,
            $dropoffInfo,
            $modeValue
        );

        Log::info('EXTERNAL BOOKING WALLET CHECK DEBUG', [
            'customer_id' => $dto->customerId,
            'customer_id_type' => gettype($dto->customerId),
            'pricing_total' => $pricing['total'],
        ]);

        // Apply discount if available
        // $user = $dto->customer ?? null; // assuming $dto has a customer property, else fetch by customer_id
        $user = User::with('wallet')
            ->findOrFail($dto->customerId);

        if (! is_string($dto->pin) || trim($dto->pin) === '') {
            throw new \InvalidArgumentException('Transaction PIN is required.');
        }

        app(TransactionPinService::class)->checkPin($user, $dto->pin);


        app(TransactionPinService::class)->checkPin($user, $dto->pin);

        $discount = $this->discountService->getUserDiscount($user);

        $discountAmount = 0;
        if ($discount) {
            $discountAmount = round(($discount->percentage / 100) * $pricing['total'], 2);
            $pricing['total'] = max($pricing['total'] - $discountAmount, 0);
        }

        // if ($user) {
        //     $this->ensureExternalUserCanPay(
        //         $user,
        //         $pricing['total']
        //     );
        // }

        $this->ensureExternalUserCanPay(
            $user,
            $pricing['total']
        );


        $this->validatePriceOverride($dto, $pricing['total']);

        $delivery = $this->createDeliveryRecord(
            $dto,
            $coordinates,
            $pricing,
            $modeValue,
            $deliveryPicsPaths,
            $discount,
            $discountAmount
        );

        // $delivery = $this->createDeliveryRecord($dto, $coordinates, $pricing, $modeOfTransport, $deliveryPicsPaths);
        $payment = $this->createPaymentRecord($dto, $delivery, $pricing);

        $this->chargeExternalUser(
            $user,
            $pricing['total'],
            $delivery,
            $payment
        );

        $this->assignDriverAndNotify($delivery, $payment);

        return [
            'delivery' => $delivery->fresh(),
            'payment' => $payment,
            'message' => $deliveryNotice,
        ];
    }

    private function ensureExternalUserCanPay(User $user, float $amount): void
    {
        $wallet = $user->wallet;

        if (! $wallet) {
            throw new \Exception('Wallet not found');
        }

        //Internal wallet balance
        $this->walletGuard->ensureCanSpend($user, $amount);

        //External (Shanono) sub-account
        $this->walletGuard->ensureExternalAccountActive(
            $wallet,
            $this->externalBank
        );

        //Merchant liquidity
        $this->walletGuard->ensureMerchantLiquidity(
            $this->externalBank,
            $amount
        );
    }


    private function chargeExternalUser(User $user, float $amount, Delivery $delivery, Payment $payment): void
    {
        DB::transaction(function () use ($user, $amount, $delivery, $payment) {

            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Extra safety check (should already be validated earlier)
            if ($wallet->available_balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            //Update wallet balances
            $wallet->update([
                'available_balance' => $wallet->available_balance - $amount,
                'total_balance'     => $wallet->total_balance - $amount,
            ]);

            //Create wallet transaction (ledger)
            WalletTransaction::create([
                'wallet_id'  => $wallet->id,
                'user_id'    => $user->id,
                'type'       => WalletTransactionTypeEnums::DEBIT,
                'amount'     => $amount,
                'description' => 'External delivery payment',
                'reference'  => $payment->reference,
                'status'     => WalletTransactionStatusEnums::SUCCESS,
                'method'     => WalletTransactionMethodEnums::WALLET,
            ]);

            //Update payment status
            $payment->update([
                'status' => 'paid',
                'meta' => array_merge($payment->meta ?? [], [
                    'wallet_debited' => true,
                    'delivery_id'   => $delivery->id,
                ]),
            ]);
        });
    }



    private function getCoordinates(ExternalBookDeliveryDTO $dto, TransportModeEnums $mode): array
    {
        return app(GoogleMapsService::class)->getCoordinatesAndDistance(
            pickupAddress: $dto->pickupLocation,
            dropoffAddress: $dto->dropoffLocation,
            mode: $mode
        );
    }

    private function reverseGeocode(float $lat, float $lng): ?array
    {
        return app(GoogleMapsService::class)->reverseGeocode($lat, $lng);
    }

    private function resolveTransportMode(ExternalBookDeliveryDTO $dto, ?array $pickupInfo, ?array $dropoffInfo, array $coordinates, TransportModeEnums $defaultMode): array
    {
        $mode = $defaultMode->value;
        $notice = null;

        if ($pickupInfo && $dropoffInfo) {
            if (strcasecmp($pickupInfo['country'], $dropoffInfo['country']) !== 0) {
                $mode = $dto->packageWeight > 50
                    ? TransportModeEnums::SHIP->value
                    : TransportModeEnums::AIR->value;

                $notice = "Delivery outside Nigeria will take longer for now";

                Log::info('International delivery detected', [
                    'pickup_country' => $pickupInfo['country'],
                    'dropoff_country' => $dropoffInfo['country'],
                ]);
            } elseif (strcasecmp($pickupInfo['state'], $dropoffInfo['state']) !== 0) {
                $dynamicMode = $this->driverService->getBestAvailableModeForDelivery(
                    $coordinates['pickup_latitude'],
                    $coordinates['pickup_longitude'],
                    $coordinates['dropoff_latitude'],
                    $coordinates['dropoff_longitude']
                );

                $mode = $dynamicMode ?? TransportModeEnums::VAN->value;
            }
        }

        return [$mode, $notice];
    }

    private function calculatePricing(
        ExternalBookDeliveryDTO $dto,
        array $coordinates,
        ?array $pickupInfo,
        ?array $dropoffInfo,
        string $modeOfTransport
    ): array {
        $pricing = $this->pricingService->calculatePriceAndETA(
            [
                'lat' => $coordinates['pickup_latitude'],
                'lng' => $coordinates['pickup_longitude'],
                'country' => $pickupInfo['country'] ?? null,
            ],
            [
                'lat' => $coordinates['dropoff_latitude'],
                'lng' => $coordinates['dropoff_longitude'],
                'country' => $dropoffInfo['country'] ?? null,
            ],
            TransportModeEnums::tryFrom($modeOfTransport) ?? TransportModeEnums::VAN,
            $dto->deliveryType
        );

        Log::info('Pricing response', ['pricing' => $pricing]);

        return $pricing;
    }

    private function validatePriceOverride(ExternalBookDeliveryDTO $dto, float $baseTotal): void
    {
        if (!is_null($dto->price_override) && $dto->price_override > $baseTotal) {
            Log::warning("Rejected price override: greater than base total price", [
                'override' => $dto->price_override,
                'base_total' => $baseTotal,
                'client' => $dto->apiClientName,
                'customer_id' => $dto->customerId,
            ]);

            throw new \Exception("Price override cannot exceed base total price");
        }
    }

    private function createDeliveryRecord(
        ExternalBookDeliveryDTO $dto,
        array $coordinates,
        array $pricing,
        string $modeOfTransport,
        array $deliveryPicsPaths,
        ?Discount $discount,
        float $discountAmount
    ): Delivery {
        $baseSubtotal = $pricing['subtotal'] ?? $pricing['total'];
        $baseTax = $pricing['tax'] ?? 0;
        $finalSubtotal = $dto->price_override ?? $baseSubtotal;
        $finalTotal = $finalSubtotal + $baseTax;

        return Delivery::create([
            'api_client_id'          => $dto->apiClientId,
            'customer_id'            => $dto->customerId,
            'external_customer_ref'  => $dto->externalCustomerRef,
            'pickup_location'        => $dto->pickupLocation,
            'dropoff_location'       => $dto->dropoffLocation,
            'sender_name'            => $dto->senderName,
            'sender_phone'           => $dto->senderPhone,
            'package_description'    => $dto->packageDescription,
            'package_weight'         => $dto->packageWeight,
            'mode_of_transportation' => $modeOfTransport,
            'delivery_type'          => $dto->deliveryType,
            'delivery_date'          => $dto->deliveryDate,
            'delivery_time'          => $dto->deliveryTime,
            'receiver_name'          => $dto->receiverName,
            'receiver_phone'         => $dto->receiverPhone,
            'package_type'           => $dto->packageType->value,
            'other_package_type'     => $dto->otherPackageType,
            'base_price'             => $baseSubtotal,
            'subsidized_price'       => $dto->price_override,
            'subtotal'               => $finalSubtotal,
            'tax'                    => $baseTax,
            'total_price'            => $finalTotal,
            'discount_id'            => $discount->id ?? null,
            'discount_amount'        => $discountAmount ?? 0,
            'commission'             => $dto->commission_override ?? $pricing['commission'] ?? 0,
            'estimated_days'         => ceil($coordinates['duration_minutes'] / (60 * 24)),
            'status'                 => DeliveryStatusEnums::BOOKED,
            'api_client_name'        => $dto->apiClientName,
            'tracking_number'        => $this->generateTrackingNumber(),
            'waybill_number'         => $this->generateWaybillNumber(),
            'pickup_latitude'        => $coordinates['pickup_latitude'],
            'pickup_longitude'       => $coordinates['pickup_longitude'],
            'dropoff_latitude'       => $coordinates['dropoff_latitude'],
            'dropoff_longitude'      => $coordinates['dropoff_longitude'],
            'distance_km'            => $coordinates['distance_km'],
            'duration_minutes'       => $coordinates['duration_minutes'],
            'estimated_arrival'      => $coordinates['eta'],
            'delivery_pics'          => $deliveryPicsPaths,
        ]);
    }

    private function createPaymentRecord(ExternalBookDeliveryDTO $dto, Delivery $delivery, array $pricing): Payment
    {
        $finalPrice = $dto->price_override ?? $pricing['subtotal'] ?? $pricing['total'];
        $finalTax = $pricing['tax'] ?? 0;
        $totalAmount = $finalPrice + $finalTax;
        $original = $dto->original_price ?? $pricing['total'];

        return Payment::create([
            'api_client_id'  => $delivery->api_client_id,
            'delivery_id'    => $delivery->id,
            'user_id'        => $delivery->customer_id,
            'reference'      => strtoupper(uniqid(self::EXTERNAL_REF_PREFIX)),
            'status'         => PaymentStatusEnums::PAID,
            'currency'       => 'NGN',
            'amount'         => $totalAmount,
            'original_price' => $original,
            'final_price'    => $totalAmount,
            'subsidy_amount' => round(max(0, $original - $totalAmount), 2),
            'meta'           => ['external_deals_flow' => true],
        ]);
    }

    private function assignDriverAndNotify(Delivery $delivery, Payment $payment): void
    {
        $driver = $this->driverService->findNearestAvailable($delivery);

        if ($driver) {
            $this->notificationService->notifyDriver($driver, $delivery);
            $this->notificationService->notifyCustomerWithDriver(
                $delivery->customer,
                $delivery,
                $driver,
                $payment
            );
        } else {
            $this->notificationService->notifyCustomerNoDriver($delivery->customer, $delivery, $payment);
            $this->notificationService->notifyAdminsNoDriver($delivery);
        }
    }

    private function generateTrackingNumber(): string
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
