<?php
// app/Enums/CommissionRole.php

namespace App\Enums;

enum BillingCycleEnums: string
{
    case MONTHLY  = 'monthly';
    case QUARTERLY   = 'quarterly';
    case SEMI_ANNUAL = 'semi_annual';
    case YEARLY  = 'yearly';
}
