<?php

namespace App\Enums;

enum TrackerStatusEnums: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case FAULTY = 'faulty';
    case RETIRED = 'retired';
    case ASSIGNED = 'assigned';
    case IN_STOCK = 'in_stock';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case DAMAGED = 'damaged';
    case LOST = 'lost';
}
