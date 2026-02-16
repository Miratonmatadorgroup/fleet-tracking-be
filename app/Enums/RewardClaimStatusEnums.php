<?php

namespace App\Enums;

enum RewardClaimStatusEnums: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PAID = 'paid';
}
