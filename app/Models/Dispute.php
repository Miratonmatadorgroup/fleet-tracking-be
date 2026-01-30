<?php

namespace App\Models;

use App\Enums\DisputeStatusEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dispute extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'tracking_number',
        'driver_contact',
        'status',
        'attachment_path',
    ];

    protected $casts = [
        'status' => DisputeStatusEnums::class,
    ];

    protected $appends = ['image_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getImageUrlAttribute(): ?string
    {
        return $this->attachment_path
            ? url(Storage::url($this->attachment_path))
            : null;
    }
}
