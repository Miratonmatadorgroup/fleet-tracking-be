<?php

namespace App\Models;

use App\Models\RewardClaim;
use App\Models\RewardCriteria;
use App\Models\RewardDeliveryLog;
use App\Enums\RewardCampaignTypeEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RewardCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reward_campaigns';

    /**
     * The primary key type (UUID).
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
        'type',
        'description',
        'reward_amount',
        'active',
        'starts_at',
        'ends_at',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'type' => RewardCampaignTypeEnums::class,
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
        'reward_amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function criteria()
    {
        return $this->hasMany(RewardCriteria::class);
    }

    public function deliveryLogs()
    {
        return $this->hasMany(RewardDeliveryLog::class);
    }

    public function claims()
    {
        return $this->hasMany(RewardClaim::class);
    }
}
