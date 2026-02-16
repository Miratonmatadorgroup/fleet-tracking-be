<?php

namespace App\Models;

use App\Enums\RidePoolingCategoryEnums;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RidePoolingPricing extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category',
        'base_price',
    ];


    /**
     * Disable auto-incrementing ID since we’re using UUIDs.
     */
    public $incrementing = false;

    /**
     * The primary key is a string type (UUID).
     */
    protected $keyType = 'string';


    /**
     * Accessor: Cast to enum if it matches, otherwise return the raw string.
     */
    public function getCategoryAttribute($value)
    {
        // Try to map to an enum, otherwise keep as plain string (for "others" custom entries)
        foreach (RidePoolingCategoryEnums::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        // Return raw string if it’s a custom category
        return $value;
    }

    /**
     * Mutator: Store only the enum value or the custom string.
     */
    public function setCategoryAttribute($value)
    {
        if ($value instanceof RidePoolingCategoryEnums) {
            $this->attributes['category'] = $value->value;
        } else {
            $this->attributes['category'] = strtolower(trim($value));
        }
    }
}
