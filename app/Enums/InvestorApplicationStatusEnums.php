<?php
namespace App\Enums;

enum InvestorApplicationStatusEnums: string
{
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
