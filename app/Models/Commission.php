<?php

namespace App\Models;

use App\Enums\CommissionRoleEnums;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $table = 'commission_settings';
      protected $fillable = [
        'role',
        'percentage',
    ];


    protected $casts = [
        'role' => CommissionRoleEnums::class,
    ];

     public $timestamps = true;
}
