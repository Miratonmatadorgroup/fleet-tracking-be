<?php

namespace App\Enums;

enum InvestorStatusEnums: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
