<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrackerTransfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'tracker_id',
        'from_user_id',
        'to_user_id',
        'performed_by',
    ];
}
