<?php

namespace App\Actions\subscription;


use App\Models\User;
use App\Models\Subscription;
use App\Enums\SubscriptionStatusEnums;


class AdminUnassignSubscriptionAction
{

    public function execute(User $user): Subscription
    {

        $subscription = Subscription::where('user_id', $user->id)
            ->active()
            ->latest()
            ->first();


        if (!$subscription) {

            throw new \Exception(
                "User does not have an active subscription."
            );

        }


        $subscription->update([

            'status' => SubscriptionStatusEnums::CANCELLED,

            'auto_renew' => false,

            'end_date' => now(),

        ]);


        return $subscription->fresh();

    }

}
