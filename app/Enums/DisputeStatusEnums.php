<?php
namespace App\Enums;

enum DisputeStatusEnums: string
{
    case PENDING   = 'pending';
    case REVIEWED = 'reviewed';
    case RESOLVED  = 'resolved';
    case RECEIVED = 'received';
    case ESCALATED =  'escalated';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
