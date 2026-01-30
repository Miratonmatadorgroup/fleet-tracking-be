<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Driver;
use App\Models\Payout;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Discount;
use Illuminate\Support\Str;
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

    protected $appends = ['image_url']; // ensures it's always returned

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



    /**
     * Get the partner record associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function partner(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Partner::class);
    }

    public function tokens()
    {
        return $this->hasMany(UserToken::class);
    }

    // FOR FLAGING
    public function flaggedRidePools()
    {
        return $this->hasMany(RidePool::class, 'flagged_by');
    }

    public function flaggedTransportModes()
    {
        return $this->hasMany(TransportMode::class, 'flagged_by');
    }

    public function flaggedDrivers()
    {
        return $this->hasMany(Driver::class, 'flagged_by');
    }

    public function assignedProjects()
    {
        return $this->belongsToMany(Project::class, 'project_user', 'user_id', 'project_id');
    }
}
