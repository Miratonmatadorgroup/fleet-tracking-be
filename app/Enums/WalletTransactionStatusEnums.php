<?php

namespace App\Enums;

enum WalletTransactionStatusEnums: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED  = 'failed';

    case REVERSED = 'reversed';
}

