<?php

namespace App\Enums;

enum RewardCampaignTypeEnums: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    case MILESTONE = 'milestone';
}
