<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\Delivery;
use App\Mail\DriverAssigned;
use Illuminate\Http\Request;
use App\Models\TransportMode;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DriverStatusEnums;
use App\Enums\PaymentStatusEnums;
use App\Enums\DeliveryStatusEnums;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Mail\PaymentSuccessCustomer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ExternalPaymentController extends Controller
{
    protected TwilioService $twilio;
    protected TermiiService $termii;

    public function __construct(TwilioService $twilio, TermiiService $termii)
    {
        $this->twilio = $twilio;
        $this->termii = $termii;
    }


    public function initiate(Request $request)
    {
        $delivery = Delivery::findOrFail($request->delivery_id);
        $apiClient = $request->attributes->get('api_client');

        // Mock payload
        $payload = [
            'reference'    => strtoupper(uniqid("PAY-")),
            'amount'       => $delivery->total_price,
            'currency'     => $request->input('currency', 'NGN'),
            'callback_url' => $request->input('callback_url'),
            'metadata'     => [
                'delivery_id'   => $delivery->id,
                'api_client_id' => $apiClient->id,
            ],
        ];

        //Pending Payment record
        Payment::updateOrCreate(
            ['delivery_id' => $delivery->id],
            [
                'reference'     => $payload['reference'],
                'status'        => PaymentStatusEnums::PENDING,
                'api_client_id' => $apiClient->id,
                'currency'      => $payload['currency'],
                'meta'          => $payload,
                'callback_url'  => $payload['callback_url'],
            ]
        );

        return successResponse('Payment initiated successfully.', $payload);
    }

    public function verify(Request $request)
    {
        $reference  = $request->query('reference');
        $deliveryId = $request->query('delivery_id');

        $payment = Payment::where('reference', $reference)
            ->where('delivery_id', $deliveryId)
            ->first();

        if (! $payment) {
            return failureResponse('Payment not found.', 404);
        }

        $delivery = Delivery::with('customer')->find($deliveryId);

        $isSuccess = true;

        if ($isSuccess) {
            if ($delivery && !$delivery->tracking_number) {
                $delivery->tracking_number = $this->generateTrackingNumber();
                $delivery->status          = DeliveryStatusEnums::BOOKED;
                $delivery->save();

                $this->notifyCustomerPaymentSuccess($delivery, $payment);

                $transport = TransportMode::with('driver')
                    ->where('type', $delivery->mode_of_transportation)
                    ->whereNotNull('driver_id')
                    ->first();

                if ($transport && $transport->driver) {
                    $driver = $transport->driver;
                    $delivery->driver_id         = $driver->id;
                    $delivery->transport_mode_id = $transport->id;
                    $delivery->save();

                    $driver->status = DriverStatusEnums::UNAVAILABLE;
                    $driver->save();

                    $this->notifyDriverAssigned($delivery, $driver);
                }
            }

            $payment->status = PaymentStatusEnums::PAID->value;
            $payment->save();

            if ($payment->callback_url) {
                dispatch(function () use ($payment) {
                    Http::post($payment->callback_url, [
                        'status'          => 'success',
                        'tracking_number' => $payment->delivery->tracking_number,
                    ]);
                });
            }

            return successResponse('Payment verified successfully.', [
                'delivery_id'     => $delivery->id,
                'tracking_number' => $delivery->tracking_number,
                'status'          => 'success',
            ]);
        }

        //Failure case
        $payment->status = PaymentStatusEnums::FAILED->value;
        $payment->save();

        return failureResponse('Payment verification failed.');
    }

    protected function notifyCustomerPaymentSuccess(Delivery $delivery, Payment $payment): void
    {
        $customer = $delivery->customer ?? (object) [
            'id'              => null,
            'name'            => $delivery->customer_name,
            'email'           => $delivery->customer_email,
            'phone'           => $delivery->customer_phone,
            'whatsapp_number' => $delivery->customer_whatsapp_number,
        ];

        $formattedAmount = number_format($payment->amount, 2);
        $msg = "Hi {$customer->name}, your LoopFreight payment of ₦{$formattedAmount} was successful.
        Tracking No: {$delivery->tracking_number}.";

        // Notify via Email
        try {
            if (!empty($customer->email)) {
                Mail::to($customer->email)->send(
                    new PaymentSuccessCustomer($delivery, $payment)
                );
            }
        } catch (\Throwable $e) {
            Log::error('Customer email failed', [
                'delivery_id' => $delivery->id,
                'customer_id' => $customer->id,
                'error'       => $e->getMessage(),
            ]);
        }

        // Notify via SMS
        try {
            if (!empty($customer->phone)) {
                $this->termii->sendSms($customer->phone, $msg);
            }
        } catch (\Throwable $e) {
            Log::error('Customer SMS failed', [
                'delivery_id' => $delivery->id,
                'customer_id' => $customer->id,
                'error'       => $e->getMessage(),
            ]);
        }

        // Notify via WhatsApp
        try {
            if (!empty($customer->whatsapp_number)) {
                $this->twilio->sendWhatsAppMessage($customer->whatsapp_number, $msg);
            }
        } catch (\Throwable $e) {
            Log::error('Customer WhatsApp failed', [
                'delivery_id' => $delivery->id,
                'customer_id' => $customer->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }



    protected function notifyDriverAssigned(Delivery $delivery, $driver): void
    {
        $customerContact = $delivery->customer_email
            ? "Email: {$delivery->customer_email}"
            : ($delivery->customer_phone
                ? "Phone: {$delivery->customer_phone}"
                : ($delivery->customer_whatsapp_number
                    ? "WhatsApp: {$delivery->customer_whatsapp_number}"
                    : "N/A"));

        // Notification message
        $msg = "Hi {$driver->name}, you have a new LoopFreight delivery scheduled.
        Tracking No: {$delivery->tracking_number}.
        Pickup: {$delivery->pickup_location}.
        Dropoff: {$delivery->dropoff_location}.
        Receiver: {$delivery->receiver_name}, Contact: {$delivery->receiver_phone}.
        Customer: {$delivery->customer_name}, Contact: {$customerContact}.
        Date: {$delivery->delivery_date} at {$delivery->delivery_time}.";

        /**
         * Notify via Email
         */
        try {
            if (!empty($driver->email)) {
                Mail::to($driver->email)->send(new DriverAssigned($delivery, $driver));
            }
        } catch (\Throwable $e) {
            Log::error('Driver email failed', [
                'delivery_id' => $delivery->id,
                'driver_id'   => $driver->id ?? null,
                'error'       => $e->getMessage(),
            ]);
        }

        /**
         * Notify via SMS
         */
        try {
            if (!empty($driver->phone)) {
                $this->termii->sendSms($driver->phone, $msg);
            }
        } catch (\Throwable $e) {
            Log::error('Driver SMS failed', [
                'delivery_id' => $delivery->id,
                'driver_id'   => $driver->id ?? null,
                'error'       => $e->getMessage(),
            ]);
        }

        /**
         * Notify via WhatsApp
         */
        try {
            if (!empty($driver->whatsapp_number)) {
                $this->twilio->sendWhatsAppMessage($driver->whatsapp_number, $msg);
            }
        } catch (\Throwable $e) {
            Log::error('Driver WhatsApp failed', [
                'delivery_id' => $delivery->id,
                'driver_id'   => $driver->id ?? null,
                'error'       => $e->getMessage(),
            ]);
        }
    }


    protected function generateTrackingNumber(): string
    {
        return 'LPF-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
    }
}
