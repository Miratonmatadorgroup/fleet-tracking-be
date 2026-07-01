<?php

namespace App\Actions\subscription;

use App\Models\User;

class GetSubscriptionUsersSummaryAction
{
    public function execute()
    {

        $usersWithSubscription = User::whereHas('subscriptions')
            ->count();

        $usersWithoutSubscription = User::whereDoesntHave('subscriptions')
            ->count();

        return [
            'users_with_subscription' => $usersWithSubscription,

            'users_without_subscription' => $usersWithoutSubscription,

            'total_users' => $usersWithSubscription + $usersWithoutSubscription,
        ];
    }
}
