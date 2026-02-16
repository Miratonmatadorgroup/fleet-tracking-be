<?php

namespace App\Models;

use App\Models\User;
use App\Models\Driver;
use App\Models\Partner;
use App\Models\Investor;
use App\Enums\PayoutStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Payout extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'driver_id',
        'partner_id',
        'investor_id',
        'amount',
        'bank_name',
        'account_number',
        'currency',
        'status',
        'provider_reference',
    ];

    protected $casts = [
        'status' => PayoutStatusEnums::class,
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
