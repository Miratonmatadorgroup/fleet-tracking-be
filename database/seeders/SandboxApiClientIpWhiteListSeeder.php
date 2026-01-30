<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApiClient;

class SandboxApiClientIpWhiteListSeeder extends Seeder
{
    public function run(): void
    {
        $sandboxClientIds = [
            'a2325b3c-b839-40b0-84f5-304933289d66', // ANP Partner - Sandbox
            '528139de-4626-4452-9eef-372a4812911f', // TNT Partner - Sandbox
            '322ac43f-a4ff-4ca3-876d-25e4ea422f29', // Shanono Bank Partner - Sandbox
        ];

        foreach ($sandboxClientIds as $clientId) {
            ApiClient::where('id', $clientId)->update([
                'ip_whitelist' => [],
            ]);
        }

        $this->command->info('Sandbox API clients updated: IP whitelist cleared.');
    }
}
