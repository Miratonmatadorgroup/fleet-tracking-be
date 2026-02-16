<?php

namespace App\Enums;

enum PaymentStatusEnums: string
{

    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELD = 'canceled';
    case FAILED = 'failed';
}
