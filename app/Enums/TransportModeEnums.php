<?php

namespace App\Enums;

enum TransportModeEnums: string
{
    case BIKE = 'bike';
    case BOAT = 'boat';
    case SHIP = 'ship';
    case AIR = 'air';
    case TRUCK = 'truck';
    case VAN = 'van';
    case BUS = 'bus';
    case CAR = 'car';
    case SUV = 'suv';
    case OTHERS = 'others';
}
