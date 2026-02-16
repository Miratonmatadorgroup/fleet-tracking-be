<?php
// app/Enums/CommissionRole.php

namespace App\Enums;

enum SubscriptionFeatureEnums: string
{
    case REAL_TIME_TRACKING = 'real_time_tracking';
    case VEHICLE_DETAILS = 'vehicle_details';
    case GEO_FENCING = 'geo_fencing';
    case REMOTE_SHUTDOWN = 'remote_shutdown';
    case SPEED_LIMIT = 'speed_limit';
    case FUEL_CONSUMPTION = 'fuel_consumption';

    case FLEET_MANAGEMENT = 'fleet_management';
}
