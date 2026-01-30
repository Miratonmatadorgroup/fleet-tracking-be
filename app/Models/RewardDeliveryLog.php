<?php

namespace App\Models;

use App\Models\RewardCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RewardDeliveryLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reward_delivery_logs';

    /**
     * Primary key is a UUID (string).
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'id',
        'reward_campaign_id',
        'driver_id',
        'delivery_id',
        'distance_km',
        'weighted_count',
        'delivery_earning',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'distance_km' => 'decimal:2',
        'weighted_count' => 'decimal:4',
        'delivery_earning' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Belongs to a Reward Campaign
    public function campaign()
    {
        return $this->belongsTo(RewardCampaign::class, 'reward_campaign_id');
    }

    // Belongs to a Driver (User)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
