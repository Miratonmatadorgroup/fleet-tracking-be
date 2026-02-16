<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TransportPricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $data = [
        ['mode_of_transportation' => 'bike', 'rate_per_kg' => 100],
        ['mode_of_transportation' => 'van', 'rate_per_kg' => 200],
        ['mode_of_transportation' => 'truck', 'rate_per_kg' => 150],
        ['mode_of_transportation' => 'bus', 'rate_per_kg' => 110],
        ['mode_of_transportation' => 'boat', 'rate_per_kg' => 200],
        ['mode_of_transportation' => 'air', 'rate_per_kg' => 1500],
        ['mode_of_transportation' => 'others', 'rate_per_kg' => 200],
    ];

    foreach ($data as $item) {
        \App\Models\TransportPricing::create($item);
    }
}

}
