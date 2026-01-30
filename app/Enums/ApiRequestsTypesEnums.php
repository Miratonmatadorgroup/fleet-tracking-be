<?php

namespace App\Enums;

enum ApiRequestsTypesEnums: string
{
    case BODY   = 'body';
    case QUERY  = 'query';
    case PATH   = 'path';
}
