<?php

namespace App\Models;

use App\Models\Merchant;
use App\Enums\TrackerStatusEnums;
use App\Enums\MerchantStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tracker extends Model
{
    use HasUuids;

    protected $table = 'trackers';

    protected $fillable = [
        'serial_number',
        'imei',
        'label',
        'status',
        'is_assigned',
        'merchant_id',
        'user_id',
        'inventory_by',
        'inventory_at',
    ];

    protected $casts = [
        'is_assigned' => 'boolean',
        'inventory_at' => 'datetime',
        'status' => TrackerStatusEnums::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Admin who inventoried the tracker
    public function inventoriedBy()
    {
        return $this->belongsTo(User::class, 'inventory_by');
    }

    // Individual owner
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Business owner
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (Optional but powerful)
    |--------------------------------------------------------------------------
    */

    public function assignToUser(User $user): void
    {
        $this->update([
            'user_id' => $user->id,
            'merchant_id' => null,
            'is_assigned' => true,
            'status' => TrackerStatusEnums::ASSIGNED,
        ]);
    }


    // App\Models\Tracker.php

    public function assignToMerchant(Merchant $merchant): void
    {
        if ($merchant->status === MerchantStatusEnums::SUSPENDED) {
            throw new \DomainException('Cannot assign tracker to suspended merchant.');
        }

        $this->update([
            'merchant_id' => $merchant->id,
            'user_id' => null,
            'is_assigned' => true,
            'status' => TrackerStatusEnums::ASSIGNED,
        ]);
    }
}
