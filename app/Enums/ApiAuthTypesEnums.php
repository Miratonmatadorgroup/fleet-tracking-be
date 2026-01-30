<?php

namespace App\Enums;

enum ApiAuthTypesEnums: string
{
    case NONE   = 'none';
    case BEARER_TOKEN = 'bearer_token';
    case API_KEY = 'api_key';
    case BASIC   = 'basic';
}
