<?php

namespace App\Enums;

enum MerchantStatusEnums: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case SUSPENDED = 'suspended';
}
