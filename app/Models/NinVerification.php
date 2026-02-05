<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\NinVerificationStatusEnums;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NinVerification extends Model
{
    use HasUuids;

    protected $table = 'nin_verifications';

    protected $fillable = [
        'user_id',
        'job_id',
        'nin_number',
        'status',
        'result',
    ];

    protected $casts = [
        'status' => NinVerificationStatusEnums::class,
        'result' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
