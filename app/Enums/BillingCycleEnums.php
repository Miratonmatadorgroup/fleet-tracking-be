<?php
// app/Enums/CommissionRole.php

namespace App\Enums;

enum BillingCycleEnums: string
{
    case MONTHLY  = 'monthly';
    case QUARTERLY   = 'quarterly';
    case YEARLY  = 'yearly';

}
