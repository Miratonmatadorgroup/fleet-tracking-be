<?php

namespace App\Http\Controllers\Api;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\RidePoolingPricing;
use App\Http\Controllers\Controller;
use App\Enums\RidePoolingCategoryEnums;
use App\DTOs\RidePoolingPricing\CreateOrUpdateRidePoolingPricingDTO;
use App\Actions\RidePoolingPricing\CreateOrUpdateRidePoolingPricingAction;

class RidePoolingPricingController extends Controller
{
    /**
     * Fetch all ride pooling pricing records.
     */
    public function index()
    {
        try {
            // Order by created_at descending
            $pricing = RidePoolingPricing::orderBy('created_at', 'desc')->get();
            return successResponse(
                'Ride pooling pricing fetched successfully.',
                ['data' => $pricing]
            );
        } catch (Throwable $th) {
            return failureResponse('Failed to fetch pricing.', 500, 'fetch_error', $th);
        }
    }


    /**
     * Create or update a ride pooling pricing record.
     */
    public function updateOrCreate(Request $request)
    {
        $enumValues = array_column(RidePoolingCategoryEnums::cases(), 'value');

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in($enumValues)],
            'custom_category' => [
                'nullable',
                'string',
                // Required if "others" is selected
                Rule::requiredIf(fn() => $request->category === RidePoolingCategoryEnums::OTHERS->value),
                'max:255',
            ],
            'base_price' => 'required|numeric|min:0',
        ]);

        try {
            $dto = new CreateOrUpdateRidePoolingPricingDTO($request);
            $pricing = CreateOrUpdateRidePoolingPricingAction::execute($dto);

            return successResponse('Ride pooling pricing created or updated successfully.', $pricing);
        } catch (Throwable $th) {
            return failureResponse('Failed to create or update pricing.', 500, 'create_or_update_error', $th);
        }
    }

    /**
     * Delete a ride pooling pricing record.
     */
    public function destroy(RidePoolingPricing $ridePoolingPricing)
    {
        try {
            $ridePoolingPricing->delete();

            return successResponse('Ride pooling pricing deleted successfully.');
        } catch (Throwable $th) {
            return failureResponse('Failed to delete pricing.', 500, 'delete_error', $th);
        }
    }
}
