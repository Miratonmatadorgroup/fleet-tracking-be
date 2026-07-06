<?php

namespace App\Enums;

enum GeoFenceActionTypeEnums: string
{
    case NONE = 'none';
    case SHUTDOWN = 'shutdown';
    case ALERT = 'alert';
}
