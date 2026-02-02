<?php

namespace App\Enums;

enum TrackerStatusEnums: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case FAULTY = 'faulty';
    case RETIRED = 'retired';
    case ASSIGNED = 'assigned';
}
