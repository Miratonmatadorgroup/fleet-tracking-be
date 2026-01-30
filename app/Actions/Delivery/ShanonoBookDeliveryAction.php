<?php

namespace App\Actions\Delivery;

use App\Models\Payment;
use App\Models\Delivery;
use App\Services\DriverService;
use App\Services\PricingService;
use App\Enums\PaymentStatusEnums;
use App\Enums\TransportModeEnums;
use App\Enums\DeliveryStatusEnums;
use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Log;
use App\Services\DeliveryTimeService;
use App\Services\NotificationService;
use App\DTOs\Delivery\ShanonoBookDeliveryDTO;

class ShanonoBookDeliveryAction
{
    protected PricingService $pricingService;
    protected DeliveryTimeService $deliveryTimeService;
    protected DriverService $driverService;
    protected NotificationService $notificationService;

    public function __construct(
        PricingService $pricingService,
        DeliveryTimeService $deliveryTimeService,
        DriverService $driverService,
        NotificationService $notificationService
    ) {
        $this->pricingService = $pricingService;
        $this->deliveryTimeService = $deliveryTimeService;
        $this->driverService = $driverService;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute booking and create both Delivery + Payment
     *
     * @return array [delivery, payment]
     */
    public function execute(ShanonoBookDeliveryDTO $dto): array
    {
        $mode = TransportModeEnums::from($dto->modeOfTransportation);

        //Banking flow
        $isBankingFlow = stripos($dto->apiClientName, 'shanono') !== false;

        if ($isBankingFlow) {
            $coordinates = app(GoogleMapsService::class)->getCoordinatesAndDistance(
                pickupAddress: 'No.3 John Great Court, Chevron, Alternative Rte, Lekki, Lagos.',
                dropoffAddress: $dto->dropoffLocation,
                mode: $mode
            );

            $pickupInfo = app(GoogleMapsService::class)->reverseGeocode(
                $coordinates['pickup_latitude'],
                $coordinates['pickup_longitude']
            );

            $dropoffInfo = app(GoogleMapsService::class)->reverseGeocode(
                $coordinates['dropoff_latitude'],
                $coordinates['dropoff_longitude']
            );

            $modeOfTransport = $mode->value; // default to user choice (BIKE in banking flow)

            // If pickup & dropoff are not in the same state → dynamic mode
            if ($pickupInfo && $dropoffInfo) {
                // First check if delivery is outside Nigeria
                if (strcasecmp($pickupInfo['country'], $dropoffInfo['country']) !== 0) {
                    // International delivery → force Air or Ship
                    $modeOfTransport = TransportModeEnums::AIR->value; // default to AIR
                    // Optionally add fallback to SHIP if heavier package
                    if ($dto->packageWeight > 50) {
                        $modeOfTransport = TransportModeEnums::SHIP->value;
                    }

                    $deliveryNotice = "Delivery outside Nigeria will take longer for now";


                    // Add a notice (not error)
                    Log::info('International delivery detected', [
                        'pickup_country'  => $pickupInfo['country'],
                        'dropoff_country' => $dropoffInfo['country'],
                        'message'         => 'Delivery outside Nigeria will take longer for now',
                    ]);
                }
                // Otherwise check if different states (domestic inter-state delivery)
                elseif (strcasecmp($pickupInfo['state'], $dropoffInfo['state']) !== 0) {
                    $dynamicMode = $this->driverService->getBestAvailableModeForDelivery(
                        $coordinates['pickup_latitude'],
                        $coordinates['pickup_longitude'],
                        $coordinates['dropoff_latitude'],
                        $coordinates['dropoff_longitude']
                    );

                    $modeOfTransport = $dynamicMode ?? TransportModeEnums::VAN->value;
                }
            }

            $delivery = Delivery::create([
                'api_client_id'            => $dto->apiClientId,
                'customer_id'              => $dto->customerId ?? config('services.logistics.bank_customer_id'),
                'external_customer_ref'    => $dto->externalCustomerRef,
                'pickup_location'          => $dto->pickupLocation,
                'dropoff_location'         => $dto->dropoffLocation,
                'customer_name'            => $dto->customerName,
                'customer_phone'           => $dto->customerPhone,
                'customer_whatsapp_number' => $dto->customerWhatsappNumber,
                'sender_name'              => $dto->senderName,
                'sender_phone'             => $dto->senderPhone,
                'package_description'      => $dto->packageDescription,
                'package_weight'           => $dto->packageWeight,
                'mode_of_transportation'   =>  $modeOfTransport,
                'delivery_type'            => $dto->deliveryType,
                'delivery_date'            => $dto->deliveryDate,
                'delivery_time'            => $dto->deliveryTime,
                'receiver_name'            => $dto->receiverName,
                'receiver_phone'           => $dto->receiverPhone,
                'package_type'             => $dto->packageType->value,
                'other_package_type'       => $dto->otherPackageType,
                'subtotal'                 => $dto->amount,
                'tax'                      => 0,
                'total_price'              => $dto->amount,
                'estimated_days'           => ceil($coordinates['duration_minutes'] / (60 * 24)),
                'status'                   => DeliveryStatusEnums::BOOKED,
                'api_client_name'          => $dto->apiClientName,
                'tracking_number'          => $this->generateTrackingNumber(),
                'pickup_latitude'          => $coordinates['pickup_latitude'],
                'pickup_longitude'         => $coordinates['pickup_longitude'],
                'dropoff_latitude'         => $coordinates['dropoff_latitude'],
                'dropoff_longitude'        => $coordinates['dropoff_longitude'],
                'distance_km'              => $coordinates['distance_km'],
                'duration_minutes'         => $coordinates['duration_minutes'],
                'estimated_arrival'        => $coordinates['eta'],
            ]);

            $payment = Payment::create([
                'api_client_id' => $delivery->api_client_id,
                'delivery_id'   => $delivery->id,
                'user_id'       => $dto->customerId ?? config('services.logistics.bank_customer_id'),
                'reference'     => strtoupper(uniqid("BANK-")),
                'status'        => PaymentStatusEnums::PAID,
                'currency'      => 'NGN',
                'amount'        => $delivery->total_price,
                'meta'          => ['banking_flow' => true],
            ]);

            //Assign nearest driver immediately
            $driver = $this->driverService->findNearestAvailable($delivery);

            if ($driver) {
                $this->notificationService->notifyDriver($driver, $delivery);
                $this->notificationService->notifyCustomerWithDriver($delivery->customer, $delivery, $driver, $payment);
            } else {
                $this->notificationService->notifyCustomerNoDriver($delivery->customer, $delivery, $payment);
                $this->notificationService->notifyAdminsNoDriver($delivery);
            }

            return ['delivery' => $delivery->fresh(), 'payment' => $payment, 'message'   => $deliveryNotice ?? null,];
        }

        throw new \LogicException("ShanonoBookDeliveryAction only supports Shanono banking flow.");
    }

    private function generateTrackingNumber(): string
    {
        return 'LPF-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }
}
