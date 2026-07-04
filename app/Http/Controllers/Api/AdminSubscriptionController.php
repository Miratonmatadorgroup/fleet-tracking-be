<?php

namespace App\Http\Controllers\Api;


use App\Actions\subscription\AdminAssignSubscriptionAction;
use App\Actions\subscription\AdminUnassignSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;


class AdminSubscriptionController extends Controller
{
    public function assign(Request $request, AdminAssignSubscriptionAction $action)
    {
        $request->validate([

            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:subscription_plans,id'

        ]);

        $user = User::findOrFail($request->user_id);
        $plan = SubscriptionPlan::where('id', $request->plan_id)->where('is_active', true)->firstOrFail();

        $subscription = $action->execute($user, $plan);

        return successResponse([
            'message' =>
            'Subscription assigned successfully',
            'subscription' =>
            $subscription->load('plan')

        ]);
    }


    public function unassign(Request $request,  AdminUnassignSubscriptionAction $action) {

        $request->validate([

            'user_id' =>'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->user_id);

        $subscription = $action->execute($user);

        return successResponse([

            'message' =>
            'Subscription removed successfully',

            'subscription' =>
            $subscription->load('plan')

        ]);
    }
}
