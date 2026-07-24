<?php
// app/Enums/CommissionRole.php

namespace App\Enums;

enum AssetTypeEnums: string
{
    case CAR  = 'car';
    case BIKE   = 'bike';
    case SUV = 'suv';
    case TRUCK  = 'truck';
    case VAN  = 'van';
    case BOAT   = 'boat';
    case HELICOPTER = 'helicopter';
    case PLANE  = 'plane';
    case SHIP  = 'ship';
    case OTHERS  = 'others';
}
