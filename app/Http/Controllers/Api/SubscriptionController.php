<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\TransactionPinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
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
