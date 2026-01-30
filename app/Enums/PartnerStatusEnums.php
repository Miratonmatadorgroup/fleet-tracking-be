<?php

namespace App\Enums;

enum PartnerStatusEnums: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
