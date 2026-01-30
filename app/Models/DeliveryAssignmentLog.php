<?php

namespace App\Models;

use App\Models\Driver;

use App\Models\Delivery;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Enums\DeliveryAssignmentLogsEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryAssignmentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'delivery_id',
        'driver_id',
        'status',
        'attempted_at',

    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'attempted_at' => 'datetime',
        'status' => DeliveryAssignmentLogsEnums::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
