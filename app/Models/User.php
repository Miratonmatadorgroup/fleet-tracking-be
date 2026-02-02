<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Driver;
use App\Models\Payout;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Discount;
use Illuminate\Support\Str;
use App\Enums\UserTypesEnums;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @method bool hasRole($roles, $guard = null)
 * @method bool assignRole($roles, $guard = null)
 * @method bool removeRole($role, $guard = null)
 * @method bool syncRoles($roles)
 * @method bool hasPermissionTo($permission, $guardName = null)
 */




/**
 * @property string $password
 */

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $guard_name = 'api';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'whatsapp_number',
        'password',
        'transaction_pin',
        'otp_code',
        'otp_expires_at',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
        'production_access_approved_at',
        'payout_restricted',
        'user_type',        // individual_operator | business_operator
        'business_type',    // co | bn | it
        'cac_number',
        'cac_document',
        'nin_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'transaction_pin',
        'remember_token',
        'pin_reset_otp',
    ];


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'bank_details_updated_at' => 'datetime',
            'production_access_approved_at' => 'datetime',
            'payout_restricted' => 'boolean',
        ];
    }

    protected $appends = ['image_url', 'cac_document_url']; // ensures it's always returned


    public function getCacDocumentUrlAttribute()
    {
        return $this->cac_document
            ? url(Storage::url($this->cac_document))
            : null;
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url(Storage::url($this->image));
        }
        return null;
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // BANK DETAILS
    public function hasBankDetails(): bool
    {
        return !empty($this->account_number)
            && !empty($this->account_name)
            && !empty($this->bank_name)
            && !empty($this->bank_code)
            && !empty($this->bank_details_updated_at);
    }


    public function isBusinessOperator(): bool
    {
        return $this->user_type === UserTypesEnums::BUSINESS_OPERATOR->value;
    }

    public function hasCacDetails(): bool
    {
        return !empty($this->business_type)
            && !empty($this->cac_number)
            && !empty($this->cac_document);
    }




    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'customer_id');
    }

    // app/Models/User.php

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    // app/Models/User.php

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    public function discounts()
    {
        return $this->belongsToMany(
            Discount::class,
            'discount_user',
            'user_id',
            'discount_id'
        );
    }


    public function tokens()
    {
        return $this->hasMany(UserToken::class);
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Trackers (Individual Ownership)
    |--------------------------------------------------------------------------
    */

    // Trackers owned directly by an individual user
    public function trackers()
    {
        return $this->hasMany(Tracker::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Actions
    |--------------------------------------------------------------------------
    */

    // Trackers inventoried by admin
    public function inventoriedTrackers()
    {
        return $this->hasMany(Tracker::class, 'inventory_by');
    }

    // Merchants verified by admin
    public function verifiedMerchants()
    {
        return $this->hasMany(Merchant::class, 'verified_by');
    }
}
