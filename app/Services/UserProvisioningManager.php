<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\DeveloperProvisioner;

class UserProvisioningManager
{
    public function provision(User $user): void
    {

        Log::info('Provision called', [
            'user_id' => $user->id,
            'is_dev' => $user->hasRole('dev'),
            'approved_at' => $user->production_access_approved_at,
        ]);
        if (!$user->hasRole('dev')) {
            app(InternalUserProvisioner::class)->provision($user);
            return;
        }

        // Always provision sandbox
        app(DeveloperProvisioner::class)->provision($user);

        // Only provision production if approved
        if ($user->production_access_approved_at) {
            Log::info('Provisioning PRODUCTION', ['user_id' => $user->id]);
            app(DeveloperProvisioner::class)->provisionProduction($user);
        }
    }
}
