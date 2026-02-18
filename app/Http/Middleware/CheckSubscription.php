<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatusEnums;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Get the latest subscription
        $subscription = $user->subscriptions()->latest()->first();

        // Check if subscription is missing or expired
        if (
            !$subscription ||
            $subscription->status !== SubscriptionStatusEnums::ACTIVE ||
            $subscription->end_date <= now()
        ) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew to access this feature.',
                'renew_url' => route('subscription.plans')
            ], 403);
        }

        return $next($request);
    }
}
