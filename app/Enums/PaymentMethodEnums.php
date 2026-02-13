<?php

namespace App\Enums;

enum PaymentMethodEnums: string
{
    case BANK      = 'bank';
    case CARD      = 'card';
    case OFFLINE   = 'offline';
    case WALLET    = 'wallet';
    case SHANONO = 'shanono';
}
