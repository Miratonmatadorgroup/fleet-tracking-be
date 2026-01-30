<?php
namespace App\Enums;

enum PartnerApplicationStatusEnums: string
{
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
