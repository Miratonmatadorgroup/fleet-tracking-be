<?php

namespace App\Actions\AssetsFleets;

use App\Models\Asset;

class GetAssetsWithTrackerAction
{
    public function execute($search = null, $perPage = 20)
    {
        return Asset::with('tracker')
            ->whereHas('tracker') // only assets that have tracker
            ->when($search, function ($query) use ($search) {

                $query->where(function ($q) use ($search) {

                    $q->where('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('license_plate', 'like', "%{$search}%")
                      ->orWhere('vin', 'like', "%{$search}%");

                });

            })
            ->paginate($perPage);
    }
}
