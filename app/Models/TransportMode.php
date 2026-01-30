<?php

namespace App\Models;

use App\Models\User;
use App\Models\Driver;
use App\Models\Delivery;
use App\Enums\TransportModeEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Enums\TransportModeCategoryEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TransportMode extends Model
{
    use HasUuids;

    protected $fillable = [
        'driver_id',
        'partner_id',
        'type',
        'category',
        'manufacturer',
        'model',
        'registration_number',
        'year_of_manufacture',
        'color',
        'passenger_capacity',
        'max_weight_capacity',
        'photo_path',
        'registration_document',
        'is_flagged',
        'flag_reason',
        'flagged_by',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function getRegistrationDocumentUrlAttribute(): ?string
    {
        return $this->registration_document ? Storage::disk('public')->url($this->registration_document) : null;
    }

    protected $appends = [
        'photo_url',
        'registration_document_url',
    ];



    protected $casts = [
        'type' => TransportModeEnums::class,
        'category' => TransportModeCategoryEnums::class,
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'transport_mode_id');
    }


    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function flaggedByUser()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
