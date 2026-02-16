<?php

namespace App\Enums;

enum WalletTransactionMethodEnums: string
{
    case MANUAL   = 'manual';
    case BANK     = 'bank';
    case TRANSFER = 'transfer';
    case SYSTEM   = 'system';
    case WALLET   = 'wallet';
    case AIRTIME = 'airtime';
    case DATA = 'data';
    case ELECTRICITY  = 'electricity';
    case CABLETV  = 'cabletv';
    case SHANONO_WALLET = 'shanono_wallet';
    case BANK_TRANSFER = 'bank_transfer';
}
