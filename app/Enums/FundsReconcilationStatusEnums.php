<?php
namespace App\Enums;


enum FundsReconcilationStatusEnums: string
{
    case NOT_PAID_OFF = 'Not_Paid_Off';
    case TOTALY_PAID_OFF = 'Totaly_Paid_off';
    case PARTLY_PAID_OFF = 'Partly_Paid_off';
}
