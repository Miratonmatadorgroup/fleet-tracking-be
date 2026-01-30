<?php

namespace App\Http\Controllers\Api\Admin;


use Throwable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\TransportPricing;
use App\Enums\TransportModeEnums;
use App\Http\Controllers\Controller;
use App\Models\TransportModePricing;
use App\DTOs\TransportPricing\UpdateTransportPricingDTO;
use App\Actions\TransportPricing\DeleteTransportPricingAction;
use App\Actions\TransportPricing\GetAllTransportPricingAction;
use App\Actions\TransportPricing\UpdateTransportPricingAction;
use Illuminate\Database\QueryException;


class TransportPricingController extends Controller
{
    public function index()
    {
        try {
            $pricing = GetAllTransportPricingAction::execute();
            return successResponse('Pricing fetched successfully.', $pricing);
        } catch (Throwable $th) {
            return failureResponse('Failed to fetch pricing.', 500, 'fetch_error', $th);
        }
    }

    public function destroy(TransportModePricing $transportPricing)
    {
        try {
            DeleteTransportPricingAction::execute($transportPricing);
            return successResponse('Pricing deleted successfully.');
        } catch (Throwable $th) {
            return failureResponse('Failed to delete pricing.', 500, 'delete_error', $th);
        }
    }

    public function updateModePricing(Request $request)
    {
        $request->validate([
            'mode' => [
                'required',
                'string',
                Rule::in(array_column(TransportModeEnums::cases(), 'value')),
            ],
            'price_per_km' => 'required|numeric|min:0',
        ]);

        try {
            $dto = new UpdateTransportPricingDTO($request);
            UpdateTransportPricingAction::execute($dto);

            return successResponse("Pricing saved successfully.");
        } catch (QueryException $e) {

            return response()->json([
                'success'     => false,
                'message'     => 'Price value is too large.',
                'dev_message' => $e->getMessage(), // keep for debugging
            ], 422);
        }
    }
}
