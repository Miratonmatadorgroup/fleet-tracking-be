<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ProductionRequestAppTypeEnums;
use App\Enums\ProductionAccessRequestStatusEnums;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionAccessRequest extends Model
{
    use HasFactory;

    /**
     * Primary key settings
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'id',
        'user_id',
        'app_type',
        'status',
        'cac_document_path',
        'cac_verification_result',
        'verified_at',
        'approved_at',
        'approved_by',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'cac_verification_result' => 'array',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',

        // Enum casting (DB stores strings)
        'status' => ProductionAccessRequestStatusEnums::class,
        'app_type' => ProductionRequestAppTypeEnums::class,
    ];

    /**
     * Automatically generate UUID
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
