<?php

namespace App\Http\Controllers\Api;

use App\Enums\BillingCycleEnums;
use App\Enums\SubscriptionFeatureEnums;
use App\Enums\UserTypesEnums;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = SubscriptionPlan::query()
            ->when(
                $request->filled('user_type'),
                fn($q) =>
                $q->where('user_type', $request->user_type)
            )
            ->when(
                $request->filled('billing_cycle'),
                fn($q) =>
                $q->where('billing_cycle', $request->billing_cycle)
            )
            ->orderByDesc('created_at')
            ->get();

        return successResponse(
            'Subscription plans fetched successfully',
            $plans
        );
    }

    public function userPlans(Request $request)
    {
        $user = Auth::user();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->when(
                !$user->hasRole(['super_admin', 'admin']),
                fn($q) => $q->where('user_type', $user->user_type)
            )
            ->when(
                $request->filled('billing_cycle'),
                fn($q) => $q->where('billing_cycle', $request->billing_cycle)
            )
            ->orderBy('price')
            ->get();

        return successResponse(
            'Subscription plans fetched successfully',
            $plans
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Store subscription plan
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        try {
            $plan = DB::transaction(function () use ($validated) {
                return SubscriptionPlan::create($validated);
            });

            return successResponse(
                'Subscription plan created successfully',
                $plan
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to create subscription plan',
                500,
                'server_error',
                $th
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update subscription plan
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, string $id)
    {
        $plan = SubscriptionPlan::find($id);

        if (! $plan) {
            return failureResponse('Subscription plan not found');
        }

        $validated = $this->validatePayload($request, $plan->id);

        try {
            DB::transaction(function () use ($plan, $validated) {
                $plan->update($validated);
            });

            return successResponse(
                'Subscription plan updated successfully',
                $plan->fresh()
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to update subscription plan',
                500,
                'server_error',
                $th
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete subscription plan
    |--------------------------------------------------------------------------
    | Recommended: soft delete via is_active
    |--------------------------------------------------------------------------
    */
    public function destroy(string $id)
    {
        $plan = SubscriptionPlan::find($id);

        if (! $plan) {
            return failureResponse('Subscription plan not found');
        }

        try {
            $plan->update([
                'is_active' => false,
            ]);

            return successResponse(
                'Subscription plan deactivated successfully'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to delete subscription plan',
                500,
                'server_error',
                $th
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Shared validator
    |--------------------------------------------------------------------------
    */
    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'user_type' => [
                'required',
                Rule::in(array_column(UserTypesEnums::cases(), 'value')),
            ],

            'billing_cycle' => [
                'required',
                Rule::in(array_column(BillingCycleEnums::cases(), 'value')),
            ],

            'price' => ['required', 'numeric', 'min:0'],

            'features' => ['required', 'array', 'min:1'],

            'features.*' => [
                'required',
                Rule::in(array_column(SubscriptionFeatureEnums::cases(), 'value')),
            ],

            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
