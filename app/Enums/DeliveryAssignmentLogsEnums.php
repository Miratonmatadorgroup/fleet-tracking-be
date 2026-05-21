<?php

namespace App\Enums;

enum DeliveryAssignmentLogsEnums: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case QUEUED = 'queued';
}
