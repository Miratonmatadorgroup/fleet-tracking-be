<?php

namespace App\Models;

use App\Models\User;
use App\Models\Delivery;
use App\Models\TransportMode;
use App\Models\DriverLocation;
use App\Enums\DriverStatusEnums;
use App\Enums\TransportModeEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use App\Enums\DriverApplicationStatusEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Driver extends Model
{
    use HasUuids, Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'whatsapp_number',
        'address',
        'status',
        'application_status',
        'transport_mode',
        'bank_name',
        'bank_code',
        'account_name',
        'account_number',
        'years_of_experience',
        'next_of_kin_name',
        'next_of_kin_phone',
        'driver_license_number',
        'license_expiry_date',
        'license_image_path',
        'national_id_number',
        'national_id_image_path',
        'profile_photo',
        'latitude',
        'longitude',
        'is_flagged',
        'flag_reason',
        'flagged_by',
    ];

    protected $casts = [
        'status' => DriverStatusEnums::class,
        'application_status' => DriverApplicationStatusEnums::class,
        'transport_mode' => TransportModeEnums::class,
        'license_expiry_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected $appends = [
        'license_image_url',
        'national_id_image_url',
        'profile_photo_url',
    ];

    public function getLicenseImageUrlAttribute(): ?string
    {
        return  $this->license_image_path
            ? Storage::disk('public')->url($this->license_image_path)
            : null;
    }

    public function getNationalIdImageUrlAttribute(): ?string
    {
        return $this->national_id_image_path
            ? Storage::disk('public')->url($this->national_id_image_path)
            : null;
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo
            ? Storage::disk('public')->url($this->profile_photo)
            : null;
    }


    public function transportModeDetails()
    {
        return $this->hasOne(TransportMode::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Driver.php
    public function partner()
    {
        return $this->hasOneThrough(
            Partner::class,
            TransportMode::class,
            'driver_id',
            'id',
            'id',
            'partner_id'
        );
    }

    public function locations()
    {
        return $this->hasMany(DriverLocation::class);
    }

    public function flaggedByUser()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
