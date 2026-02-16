<?php

namespace App\Enums;

enum NinVerificationStatusEnums: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
