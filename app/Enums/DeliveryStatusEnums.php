<?php

namespace App\Enums;

enum DeliveryStatusEnums: string
{
    case SCHEDULED = 'scheduled';
    case BOOKED = 'booked';
    case QUEUED = 'queued';
    case IN_TRANSIT = 'in_transit';
    case COMPLETED = 'completed';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case PENDING_PAYMENT = 'pending_payment';
}
