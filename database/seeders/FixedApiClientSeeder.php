<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class FixedApiClientSeeder extends Seeder
{
    // FOR LOCAL
    // public function run(): void
    // {
    //     $partners = [
    //         [
    //             'name'        => 'External Partner',
    //             'customer_id' => '373add09-10fa-42b5-b8aa-374a767358c9',
    //         ],
    //         [
    //             'name'        => 'Shanono Bank Partner',
    //             'customer_id' => 'f93cb646-2586-4636-8db8-99f5c094a123',
    //         ],
    //     ];

    //     $environments = [
    //         'sandbox',
    //         'production',
    //     ];

    //     foreach ($partners as $partner) {
    //         foreach ($environments as $env) {
    //             ApiClient::updateOrCreate(
    //                 [
    //                     'name'        => $partner['name'] . ' - ' . ucfirst($env),
    //                     'environment' => $env,
    //                 ],
    //                 [
    //                     'api_key'     => Str::random(40),
    //                     'active'      => true,
    //                     'ip_whitelist'=> ['127.0.0.1'],
    //                     'customer_id' => $partner['customer_id'],
    //                 ]
    //             );
    //         }
    //     }
    // }

    // FOR STAGING
    public function run(): void
    {
        $partners = [
            [
                'name'        => 'External Partner',
                'customer_id' => '5909ff28-30e3-45dc-8596-b1c2423b5a9d',
            ],
            [
                'name'        => 'Shanono Bank Partner',
                'customer_id' => 'd0b7b3a2-d74a-40ac-8888-0542d645730d',
            ],
        ];

        $environments = [
            'sandbox',
            'production',
        ];

        foreach ($partners as $partner) {
            foreach ($environments as $env) {
                ApiClient::updateOrCreate(
                    [
                        'name'        => $partner['name'] . ' - ' . ucfirst($env),
                        'environment' => $env,
                    ],
                    [
                        'api_key'     => Str::random(40),
                        'active'      => true,
                        'ip_whitelist'=> ['127.0.0.1'],
                        'customer_id' => $partner['customer_id'],
                    ]
                );
            }
        }
    }
}
