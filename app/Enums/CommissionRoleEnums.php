<?php
// app/Enums/CommissionRole.php

namespace App\Enums;

enum CommissionRoleEnums: string
{
    case DRIVER   = 'driver';
    case PARTNER  = 'partner';
    case INVESTOR = 'investor';
    case REFERRER = 'referrer';
    case PLATFORM = 'platform';
}
