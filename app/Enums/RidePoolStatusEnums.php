<?php



namespace App\Enums;

enum RidePoolStatusEnums: string
{
    case PENDING = 'pending';
    case BOOKED = 'booked';
    case IN_TRANSIT = 'in_transit';
    case ARRIVED = 'arrived';
    case FLAGGED = 'flagged';
    case RIDE_STARTED = 'ride_started';
    case RIDE_ENDED = 'ride_ended';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
