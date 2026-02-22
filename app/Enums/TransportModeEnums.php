<?php

namespace App\Enums;

enum TransportModeEnums: string
{
    case ROAD = 'road';
    case RAIL = 'rail';
    case WATER = 'water';
    case MARINE = 'marine';
    case AIR = 'air';
    case OTHERS = 'others';
}
