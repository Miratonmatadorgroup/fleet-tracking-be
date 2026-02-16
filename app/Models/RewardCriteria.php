<?php

namespace App\Models;

use App\Models\RewardCampaign;
use App\Enums\RewardCriteriaUnitEnums;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RewardCriteriaOperatorEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RewardCriteria extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reward_criteria';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'reward_campaign_id',
        'metric',
        'operator',
        'value',
        'unit',
    ];

    protected $casts = [
        'operator' => RewardCriteriaOperatorEnums::class,
        'unit' => RewardCriteriaUnitEnums::class,
    ];

    // App\Models\RewardCriteria.php
    public function campaign()
    {
        return $this->belongsTo(RewardCampaign::class, 'reward_campaign_id');
    }
}
