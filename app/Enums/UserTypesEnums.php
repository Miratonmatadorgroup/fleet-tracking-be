<?php
// app/Enums/CommissionRole.php

namespace App\Enums;

enum UserTypesEnums: string
{
    case INDIVIDUAL_OPERATOR   = 'individual_operator';
    case BUSINESS_OPERATOR  = 'business_operator';

}
