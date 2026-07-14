<?php

namespace App\Actions\AssetsFleets;

use App\Models\Asset;

class GetAssetsWithTrackerAction
{
    public function execute($search = null, $perPage = 20)
    {
        $assets = Asset::with([
            'tracker',
            'geofences:id,name,radius_meters,action'
        ])
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

        $assets->getCollection()->transform(function ($asset) {

            $geofence = $asset->geofences->first();

            $asset->geofence = $geofence ? [
                'id' => $geofence->id,
                'name' => $geofence->name,
                'radius_meters' => $geofence->radius_meters,
                'action' => $geofence->action,
            ] : null;

            // Remove the relationship if the frontend only needs one object
            unset($asset->geofences);

            return $asset;
        });

        return $assets;
    }
}

// class GetAssetsWithTrackerAction
// {
//     public function execute($search = null, $perPage = 20)
//     {
//         return Asset::with('tracker')
//             ->whereHas('tracker') // only assets that have tracker
//             ->when($search, function ($query) use ($search) {

//                 $query->where(function ($q) use ($search) {

//                     $q->where('make', 'like', "%{$search}%")
//                       ->orWhere('model', 'like', "%{$search}%")
//                       ->orWhere('license_plate', 'like', "%{$search}%")
//                       ->orWhere('vin', 'like', "%{$search}%");

//                 });

//             })
//             ->paginate($perPage);
//     }
// }
