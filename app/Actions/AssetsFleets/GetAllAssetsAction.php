<?php

namespace App\Actions\AssetsFleets;

use App\Models\Asset;

class GetAllAssetsAction
{
    public function execute(?string $search = null, int $perPage = 20)
    {
        $query = Asset::with([
            'tracker.user',
            'tracker.merchant',
            'driver',
            'organization'
        ])
            ->orderBy('created_at', 'desc');

        if (!empty($search)) {

            $search = strtolower(trim($search));

            $driver = $query->getConnection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $driver, $likeOperator) {

                // Asset ID
                if ($driver === 'pgsql') {
                    $q->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                } else {
                    $q->where('id', $likeOperator, "%{$search}%");
                }

                // Asset fields
                $q->orWhereRaw("LOWER(asset_type) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(make) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(model) {$likeOperator} ?", ["%{$search}%"]);

                // Year
                if ($driver === 'pgsql') {
                    $q->orWhereRaw("CAST(year AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                } else {
                    $q->orWhereRaw("CAST(year AS CHAR) {$likeOperator} ?", ["%{$search}%"]);
                }

                // Continue the chain
                $q->orWhereRaw("LOWER(license_plate) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(COALESCE(vin, '')) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(status) {$likeOperator} ?", ["%{$search}%"])

                    ->orWhereHas('tracker', function ($tracker) use ($search, $likeOperator) {
                        $tracker->whereRaw("LOWER(serial_number) {$likeOperator} ?", ["%{$search}%"])
                            ->orWhereRaw("LOWER(imei) {$likeOperator} ?", ["%{$search}%"])
                            ->orWhereRaw("LOWER(status) {$likeOperator} ?", ["%{$search}%"]);
                    })

                    ->orWhereHas('tracker.user', function ($user) use ($search, $likeOperator) {
                        $user->whereRaw("LOWER(name) {$likeOperator} ?", ["%{$search}%"])
                            ->orWhereRaw("LOWER(email) {$likeOperator} ?", ["%{$search}%"]);
                    })

                    ->orWhereHas('driver', function ($driverQuery) use ($search, $driver, $likeOperator) {
                        if ($driver === 'pgsql') {
                            $driverQuery->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                        } else {
                            $driverQuery->where('id', $likeOperator, "%{$search}%");
                        }
                    })

                    ->orWhereHas('organization', function ($orgQuery) use ($search, $driver, $likeOperator) {
                        if ($driver === 'pgsql') {
                            $orgQuery->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                        } else {
                            $orgQuery->where('id', $likeOperator, "%{$search}%");
                        }
                    });
            });
        }

        $assets = $query->paginate($perPage);

        // Your transform() remains unchanged...

        return $assets;
    }
    // public function execute(?string $search = null, int $perPage = 20)
    // {
    //     $query = Asset::with([
    //         'tracker.user',
    //         'tracker.merchant',
    //         'driver',
    //         'organization'
    //     ])
    //         ->orderBy('created_at', 'desc');

    //     if (!empty($search)) {
    //         $search = strtolower($search);

    //         $query->where(function ($q) use ($search) {

    //             $driver = $q->getConnection()->getDriverName();

    //             $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

    //             if ($driver === 'pgsql') {
    //                 $q->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
    //             } else {
    //                 $q->whereRaw("LOWER(id) {$likeOperator} ?", ["%{$search}%"]);
    //             }

    //             $q->orWhereRaw("LOWER(license_plate) {$likeOperator} ?", ["%{$search}%"])
    //                 ->orWhereRaw("LOWER(vin) {$likeOperator} ?", ["%{$search}%"])

    //                 ->orWhereHas('tracker', function ($t) use ($search, $likeOperator) {
    //                     $t->whereRaw("LOWER(serial_number) {$likeOperator} ?", ["%{$search}%"]);
    //                 })

    //                 ->orWhereHas('tracker.user', function ($u) use ($search, $likeOperator) {
    //                     $u->whereRaw("LOWER(name) {$likeOperator} ?", ["%{$search}%"]);
    //                 });
    //         });
    //     }

    //     $assets = $query->paginate($perPage);

    //     $assets->getCollection()->transform(function ($asset) {

    //         $tracker = $asset->tracker;
    //         $user    = $tracker?->user;

    //         return [
    //             'id' => $asset->id,
    //             'asset_type' => $asset->asset_type,
    //             'make' => $asset->make,
    //             'model' => $asset->model,
    //             'year' => $asset->year,
    //             'license_plate' => $asset->license_plate,
    //             'vin' => $asset->vin,
    //             'status' => $asset->status,

    //             'tracker' => $tracker ? [
    //                 'id' => $tracker->id,
    //                 'serial_number' => $tracker->serial_number,
    //                 'imei' => $tracker->imei,
    //                 'status' => $tracker->status,
    //             ] : null,

    //             'user' => $user ? [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //             ] : null,

    //             'driver' => $asset->driver ? [
    //                 'id' => $asset->driver->id,
    //             ] : null,

    //             'organization' => $asset->organization ? [
    //                 'id' => $asset->organization->id,
    //             ] : null,

    //             'created_at' => $asset->created_at,
    //         ];
    //     });

    //     return $assets;
    // }
}
