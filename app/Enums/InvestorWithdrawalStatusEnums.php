<?php

namespace App\Enums;

enum InvestorWithdrawalStatusEnums: string
{
    case NONE = 'none';
    case PROCESSING = 'processing';
    case REFUNDED = 'refunded';
}
