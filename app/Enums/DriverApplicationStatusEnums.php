<?php
namespace App\Enums;

enum DriverApplicationStatusEnums: string
{
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
