<?php

namespace App\Enums;

enum SubscriptionStatusEnums: string
{
    case PENDING = "pending";
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
