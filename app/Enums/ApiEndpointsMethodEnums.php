<?php

namespace App\Enums;

enum ApiEndpointsMethodEnums: string
{
    case GET   = 'get';
    case POST     = 'post';
    case PUT = 'put';
    case DELETE   = 'delete';
}
