<?php

namespace App\Enums;

enum SubscriptionStatusEnums: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
