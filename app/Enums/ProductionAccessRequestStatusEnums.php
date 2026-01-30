<?php

namespace App\Enums;

enum ProductionAccessRequestStatusEnums: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case APPROVED  = 'approved';

    case RJECTED = 'rejected';
}

