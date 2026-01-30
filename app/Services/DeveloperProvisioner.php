<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\ApiClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\ExternalBankService;

class DeveloperProvisioner
{
    public function provision(User $user): void
    {
        // Wallet MUST already exist
        if (!$user->wallet) {
            throw new \RuntimeException('Wallet missing for developer provisioning');
        }

        ApiClient::firstOrCreate(
            [
                'customer_id' => $user->id,
                'environment' => 'sandbox',
            ],
            [
                'name'         => strtoupper(Str::slug($user->name, '_')) . '_PARTNER_SANDBOX',
                'active'       => true,
                'ip_whitelist' => [],
            ]
        );
    }

    public function provisionProduction(User $user): void
    {
        // Ensure user has wallet and sandbox credentials already
        if (!$user->wallet) {
            throw new \RuntimeException('Wallet missing for developer provisioning');
        }

        // Check if production credentials already exist
        $apiClient = ApiClient::firstOrCreate(
            [
                'customer_id' => $user->id,
                'environment' => 'production',
            ],
            [
                'name'         => strtoupper(Str::slug($user->name, '_')) . '_PARTNER_LIVE',
                'active'       => true,
                'ip_whitelist' => [],
            ]
        );
    }
}
