<?php

namespace App\Models;

use Illuminate\Support\Str;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Enums\TransportModeCategoryEnums;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RidePool extends Model
{
    protected $table = 'ride_pools';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'driver_id',
        'transport_mode_id',
        'pickup_location',
        'dropoff_location',
        'start_time',
        'end_time',
        'duration',
        'estimated_cost',
        'eta_minutes',
        'eta_timestamp',
        'status',
        'payment_status',
        'ride_date',
        'ride_pool_category',
        'partner_id',
        'driver_accepted_at',
        'rated',
        'is_flagged',
        'flag_reason',
        'flagged_by',
        'cancelled_by',
        'cancelled_by_admin_id',
    ];

    protected $casts = [
        'pickup_location'  => 'array',
        'dropoff_location' => 'array',
        'ride_date'        => 'datetime',
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'rated'            => 'boolean',
        'status'             => RidePoolStatusEnums::class,
        'payment_status'     => RidePoolPaymentStatusEnums::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
    public function transportMode()
    {
        return $this->belongsTo(TransportMode::class);
    }
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function flaggedByUser()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
