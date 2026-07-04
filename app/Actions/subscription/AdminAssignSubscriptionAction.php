<?php

namespace App\Actions\subscription;


use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Enums\SubscriptionStatusEnums;
use Illuminate\Support\Facades\DB;

class AdminAssignSubscriptionAction
{

    public function execute(
        User $user,
        SubscriptionPlan $plan,
        ?int $months = 1
    ): Subscription {


        // Prevent duplicate active subscription
        $existing = Subscription::where('user_id', $user->id)
            ->active()
            ->first();


        if ($existing) {
            throw new \Exception(
                "User already has an active subscription."
            );
        }


        $startDate = now();

        $endDate = match($plan->billing_cycle->value){

            'monthly' =>
                now()->addMonth(),

            'quarterly' =>
                now()->addMonths(3),

            'yearly' =>
                now()->addYear(),

            default =>
                now()->addMonth()
        };


        return DB::transaction(function () use (
            $user,
            $plan,
            $startDate,
            $endDate
        ){

            return Subscription::create([

                'user_id' => $user->id,

                'plan_id' => $plan->id,

                'asset_id' => $user->asset_id ?? null,


                'start_date' => $startDate,

                'end_date' => $endDate,


                'status' =>
                    SubscriptionStatusEnums::ACTIVE,


                'price_per_month' =>
                    $plan->price,


                'payment_method' =>
                    'admin_assigned',


                'auto_renew' => false,


                'is_trial' => false,

            ]);

        });


    }

}
