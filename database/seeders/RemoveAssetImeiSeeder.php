<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class RemoveAssetImeiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Asset::where(
            'id',
            '019dd409-9389-738f-9419-1ea2d7123cac'
        )->update([
            'imei' => null,
        ]);
    }
}
