<?php

namespace App\Http\Controllers\Api;


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
