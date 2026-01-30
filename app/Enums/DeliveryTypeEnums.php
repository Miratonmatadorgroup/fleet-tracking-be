<?php

namespace App\Enums;

enum DeliveryTypeEnums: string
{
    case STANDARD = 'standard';
    case NEXT_DAY = 'next_day';
    case QUICK = 'quick';
}
