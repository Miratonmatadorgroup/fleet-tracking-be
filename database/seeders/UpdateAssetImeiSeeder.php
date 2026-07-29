<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class UpdateAssetImeiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Asset::where(
            'id',
            '019facf1-b77f-707b-b984-c051012e3e1f'
        )->update([
            'imei' => '866557082412216',
        ]);
    }
}
