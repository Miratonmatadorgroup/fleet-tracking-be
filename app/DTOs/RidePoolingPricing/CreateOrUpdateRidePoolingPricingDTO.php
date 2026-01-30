<?php

namespace App\DTOs\RidePoolingPricing;

use Illuminate\Http\Request;
use App\Enums\RidePoolingCategoryEnums;
use InvalidArgumentException;

class CreateOrUpdateRidePoolingPricingDTO
{
    public string $category;
    public float $base_price;

    public function __construct(Request $request)
    {
        $category = $request->input('category');
        $customCategory = $request->input('custom_category'); // optional

        if ($category === RidePoolingCategoryEnums::OTHERS->value) {
            if (empty($customCategory)) {
                throw new InvalidArgumentException(
                    'Custom category name is required when category is "others".'
                );
            }

            // Store the actual custom category value directly in "category"
            $this->category = strtolower(trim($customCategory));
        } else {
            $enumValues = array_column(RidePoolingCategoryEnums::cases(), 'value');
            if (!in_array($category, $enumValues, true)) {
                throw new InvalidArgumentException("Invalid category: {$category}");
            }
            $this->category = $category;
        }

        $this->base_price = (float) $request->input('base_price', 0);
    }
}
