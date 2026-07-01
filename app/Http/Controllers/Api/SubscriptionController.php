<?php

namespace App\Http\Controllers\Api;


use App\Actions\subscription\GetSubscriptionUsersSummaryAction;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\TransactionPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{

    public function listSubscriptions()
    {
        $user = Auth::user();

        $subscriptions = Subscription::with('plan')
            ->latest()
            ->get()
            ->map(function ($subscription) {
                return [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'auto_renew' => (bool) $subscription->auto_renew,
                    'is_trial' => (bool) $subscription->is_trial,
                    'payment_method' => $subscription->payment_method,

                    'plan' => [
                        'id' => $subscription->plan?->id,
                        'name' => $subscription->plan?->name,
                        'user_type' => $subscription->plan?->user_type,
                        'billing_cycle' => $subscription->plan?->billing_cycle,
                        'price' => $subscription->plan?->price,
                        'features' => $subscription->plan?->features ?? [],
                    ]
                ];
            });

        return successResponse([
            'subscriptions' => $subscriptions
        ]);
    }

    public function mySubscriptions()
    {
         /** @var \App\Models\User $user */
        $user = Auth::user();

        $subscriptions = $user
            ->subscriptions()
            ->with('plan')
            ->latest()
            ->get();


        return successResponse([
            'count' => $subscriptions->count(),
            'subscriptions' => $subscriptions
        ]);
    }

    public function subscriptionUsersSummary(GetSubscriptionUsersSummaryAction $action)
    {
        try {

            $data = $action->execute();


            return successResponse(
                'Subscription users summary retrieved successfully',
                $data
            );
        } catch (\Throwable $th) {


            return failureResponse(
                'Failed to retrieve subscription summary',
                500,
                'subscription_summary_error',
                $th
            );
        }
    }

    public function toggleAutoRenew(Request $request, $subscriptionId)
    {
        $request->validate([
            'transaction_pin' => 'required|string'
        ]);

        $user = Auth::user();

        // Verify transaction PIN
        app(TransactionPinService::class)
            ->checkPin($user, $request->transaction_pin);

        $subscription = Subscription::where('user_id', $user->id)
            ->findOrFail($subscriptionId);

        $subscription->update([
            'auto_renew' => !$subscription->auto_renew
        ]);

        return successResponse([
            'message' => 'Auto renew updated successfully',
            'auto_renew' => $subscription->auto_renew
        ]);
    }
}
