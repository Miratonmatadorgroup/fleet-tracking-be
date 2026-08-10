<?php

namespace App\Services;

use App\Enums\AssetTypeEnums;
use App\Enums\TrackerStatusEnums;
use App\Models\Asset;
use App\Models\GeofenceBreach;
use App\Models\Tracker;

class SuperAdminAnalyticsService
{
    public function getAnalytics(): array
    {
        /*
        |--------------------------------------------------------------------------
        | TRACKERS
        |--------------------------------------------------------------------------
        |
        | Active tracker means:
        | - status = ACTIVE
        | - is_online = true
        |
        */

        $totalTrackers = Tracker::count();

        $activeTrackers = Tracker::query()
            ->where('status', TrackerStatusEnums::ACTIVE)
            ->where('is_online', true)
            ->count();


        /*
        |--------------------------------------------------------------------------
        | ACTIVE ASSETS
        |--------------------------------------------------------------------------
        |
        | An active asset means:
        | - asset has a tracker
        | - tracker status = ACTIVE
        | - tracker is_online = true
        |
        */

        $activeAssetQuery = Asset::whereHas('tracker', function ($query) {
            $query
                ->where('status', TrackerStatusEnums::ACTIVE)
                ->where('is_online', true);
        });

        $totalActiveAssets = (clone $activeAssetQuery)->count();


        /*
        |--------------------------------------------------------------------------
        | ACTIVE ASSETS BY TYPE
        |--------------------------------------------------------------------------
        |
        | Only assets with active + online trackers are counted.
        |
        */

        $activeAssetsByType = [
            'vans' => (clone $activeAssetQuery)
                ->where('asset_type', AssetTypeEnums::VAN)
                ->count(),

            'cars' => (clone $activeAssetQuery)
                ->where('asset_type', AssetTypeEnums::CAR)
                ->count(),

            'trucks' => (clone $activeAssetQuery)
                ->where('asset_type', AssetTypeEnums::TRUCK)
                ->count(),

            'bikes' => (clone $activeAssetQuery)
                ->where('asset_type', AssetTypeEnums::BIKE)
                ->count(),
        ];


        /*
        |--------------------------------------------------------------------------
        | FAULTY ASSETS
        |--------------------------------------------------------------------------
        |
        | An asset is considered faulty when:
        |
        | - it has a tracker
        | - AND the tracker is either:
        |      1. not ACTIVE
        |      OR
        |      2. not ONLINE
        |
        */

        $faultyAssets = Asset::whereHas('tracker', function ($query) {
            $query->where(function ($q) {
                $q->where('status', '!=', TrackerStatusEnums::ACTIVE)
                    ->orWhere('is_online', false)
                    ->orWhereNull('is_online');
            });
        })->count();


        /*
        |--------------------------------------------------------------------------
        | GEOFENCE BREACHED ASSETS
        |--------------------------------------------------------------------------
        |
        | Count unique assets that:
        |
        | - have a tracker
        | - have an active geofence
        | - have recorded a geofence breach
        |
        */

        $geofenceBreachedAssets = GeofenceBreach::query()
            ->whereNotNull('asset_id')
            ->whereHas('asset.tracker')
            ->whereHas('geofence', function ($query) {
                $query->where('is_active', true);
            })
            ->distinct('asset_id')
            ->count('asset_id');


        /*
        |--------------------------------------------------------------------------
        | RETURN ANALYTICS
        |--------------------------------------------------------------------------
        */

        return [
            'trackers' => [
                'total' => $totalTrackers,
                'active' => $activeTrackers,
            ],

            'active_assets' => [
                'total' => $totalActiveAssets,

                'by_type' => $activeAssetsByType,
            ],

            'faulty_assets' => $faultyAssets,

            'geofence_breached_assets' => $geofenceBreachedAssets,
        ];
    }
}
