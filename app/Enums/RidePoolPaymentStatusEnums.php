<?php



namespace App\Enums;

enum RidePoolPaymentStatusEnums: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
