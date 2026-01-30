<?php

namespace App\Enums;

enum PackageTypeEnums: string
{
    case ELECTRONICS = 'electronics';
    case CLOTHING = 'clothing';
    case DOCUMENTS = 'documents';
    case FOOD_ITEMS = 'food_items';
    case FRAGILE_ITEMS = 'fragile_items';
    case OTHERS = 'others';
}
