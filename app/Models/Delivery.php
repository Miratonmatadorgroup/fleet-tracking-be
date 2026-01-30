<?php

namespace App\Models;

use App\Models\Driver;
use App\Models\ApiClient;
use App\Models\TransportMode;
use App\Enums\TransportModeEnums;
use App\Enums\DeliveryStatusEnums;
use App\Models\FundReconciliation;
use App\Models\DeliveryAssignmentLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Delivery extends Model
{

    use HasUuids;

    protected $table = 'deliveries';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'driver_assigned_at',
        'pickup_location',
        'dropoff_location',
        'mode_of_transportation',
        'package_type',
        'other_package_type',
        'package_description',
        'package_weight',
        'delivery_pics',
        'delivery_date',
        'tracking_number',
        'waybill_number',
        'delivery_time',
        'special_instructions',
        'sender_name',
        'sender_phone',
        'sender_email',
        'sender_whatsapp_number',
        'receiver_name',
        'receiver_phone',
        'subtotal',
        'tax',
        'total_price',
        'discount_id',
        'discount_amount',
        'subsidized_price',
        'base_price',
        'status',
        'delivery_type',
        'estimated_days',
        'distance_km',
        'duration_minutes',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',

        'api_client_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_whatsapp_number',
        'external_reference',
        'source_channel',
    ];

    protected $casts = [
        'delivery_date' => 'date:Y-m-d',
        'delivery_time' => 'datetime:H:i',
        'driver_assigned_at' => 'datetime',
        'delivery_pics' => 'array',
        'subtotal' => 'float',
        'tax' => 'float',
        'total_price' => 'float',
        'base_price' => 'float',
        'subsidized_price' => 'float',
        'mode_of_transportation' => TransportModeEnums::class,
        'status' => DeliveryStatusEnums::class,
    ];
    protected $appends = ['delivery_pics_urls'];


    public function getDeliveryPicsUrlsAttribute(): ?array
    {
        if (!$this->delivery_pics) {
            return null;
        }

        $paths = $this->delivery_pics;

        if (!is_array($paths) || empty($paths)) {
            return null;
        }

        return array_map(fn($path) => Storage::disk('public')->url($path), $paths);
    }



    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function transportMode()
    {
        return $this->belongsTo(TransportMode::class, 'transport_mode_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }


    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function getTransportPartnerAttribute()
    {
        return optional($this->transportMode?->partner?->user);
    }


    public function investor()
    {
        return $this->belongsTo(User::class, 'investor_id');
    }


    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    // Delivery.php
    public function fundReconciliation()
    {
        return $this->hasOne(FundReconciliation::class, 'api_client_id', 'api_client_id');
    }


    public function getRouteKeyName()
    {
        return 'id';
    }


    public function assignmentLogs()
    {
        return $this->hasMany(DeliveryAssignmentLog::class);
    }
}
