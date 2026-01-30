<?php

namespace App\Models;

use App\Models\User;
use App\Models\TransportMode;
use App\Enums\PartnerStatusEnums;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PartnerApplicationStatusEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'business_name',
        'address',
        'status',
        'application_status',
        'bank_name',
        'account_name',
        'account_number',
        'bank_codee',

    ];

    protected $casts = [
        'status' => PartnerStatusEnums::class,
        'application_status' => PartnerApplicationStatusEnums::class,
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transportModes(): HasMany
    {
        return $this->hasMany(TransportMode::class);
    }
}
