<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ApiClient;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class ApiClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // public function run(): void
    // {
    //     $partners = [
    //         'Shanono Bank Partner',
    //         'External Partner',
    //     ];

    //     $environments = [
    //         'sandbox',
    //         'production',
    //     ];

    //     foreach ($partners as $partner) {
    //         foreach ($environments as $env) {
    //             ApiClient::updateOrCreate(
    //                 [
    //                     'name' => $partner . ' - ' . ucfirst($env),
    //                     'environment' => $env,
    //                 ],
    //                 [
    //                     'id' => Str::uuid(),
    //                     'api_key' => Str::random(40),
    //                     'active' => true,
    //                     'ip_whitelist' => json_encode(['127.0.0.1']),
    //                 ]
    //             );
    //         }
    //     }
    // }


    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $partners = [
             [
                'name' => 'External Partner',
                'email' => 'externalpartner@system.com',
            ],
            [
                'name' => 'Shanono Bank Partner',
                'email' => 'poskanzee@gmail.com',
            ],
             [
                'name' => 'TNT Partner',
                'email' => 'derektippy@aol.com',
            ],
            [
                'name' => 'ANP Partner',
                'email' => 'cathsroge59@aol.com',
            ],
        ];

        $environments = [
            'sandbox'
        ];

        foreach ($partners as $partner) {

            $user = User::where('email', $partner['email'])->first();

            if (!$user) {

                continue;
            }

            foreach ($environments as $env) {
                ApiClient::updateOrCreate(
                    [
                        'name' => $partner['name'] . ' - ' . ucfirst($env),
                        'environment' => $env,
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'api_key' => Str::random(40),
                        'active' => true,
                        'ip_whitelist' => [],
                        'customer_id' => $user->id,
                    ]
                );
            }
        }
    }
}
