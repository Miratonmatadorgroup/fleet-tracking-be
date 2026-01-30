<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Payment;
use App\Services\NotificationService;

class DeliveryAssignmentService
{
    private NotificationService $notifier;

    public function __construct(NotificationService $notifier)
    {
        $this->notifier = $notifier;
    }

    public function notifyParties(Delivery $delivery, ?Driver $driver, ?Payment $payment): void
    {
        $customer = $delivery->customer;

        if ($driver) {
            $this->notifier->notifyDriver($driver, $delivery);
            $this->notifier->notifyCustomerWithDriver($customer, $delivery, $driver, $payment);
        } else {
            $this->notifier->notifyCustomerNoDriver($customer, $delivery, $payment);
            $this->notifier->notifyAdminsNoDriver($delivery);
        }
    }
}
