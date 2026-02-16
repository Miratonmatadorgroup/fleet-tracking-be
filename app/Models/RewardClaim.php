<?php

namespace App\Models;

use App\Models\RewardCampaign;
use App\Models\WalletTransaction;
use App\Enums\RewardClaimStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RewardClaim extends Model
{

    use HasFactory, HasUuids;

    protected $table = 'reward_claims';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reward_campaign_id',
        'driver_id',
        'claim_period',
        'amount',
        'status',
        'wallet_transaction_id',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => RewardClaimStatusEnums::class,
        'amount' => 'float',
        'claim_period' => 'date',
    ];

    /**
     * Relationships
     */
    public function campaign()
    {
        return $this->belongsTo(RewardCampaign::class, 'reward_campaign_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
