<?php

namespace App\Enums;

enum DriverStatusEnums: string
{
    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
