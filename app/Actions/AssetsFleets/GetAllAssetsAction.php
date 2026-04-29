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
            $search = strtolower($search);

            $query->where(function ($q) use ($search) {

                $driver = $q->getConnection()->getDriverName();

                $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

                if ($driver === 'pgsql') {
                    $q->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                } else {
                    $q->whereRaw("LOWER(id) {$likeOperator} ?", ["%{$search}%"]);
                }

                $q->orWhereRaw("LOWER(license_plate) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(vin) {$likeOperator} ?", ["%{$search}%"])

                    ->orWhereHas('tracker', function ($t) use ($search, $likeOperator) {
                        $t->whereRaw("LOWER(serial_number) {$likeOperator} ?", ["%{$search}%"]);
                    })

                    ->orWhereHas('tracker.user', function ($u) use ($search, $likeOperator) {
                        $u->whereRaw("LOWER(name) {$likeOperator} ?", ["%{$search}%"]);
                    });
            });
        }

        $assets = $query->paginate($perPage);

        $assets->getCollection()->transform(function ($asset) {

            $tracker = $asset->tracker;
            $user    = $tracker?->user;

            return [
                'id' => $asset->id,
                'asset_type' => $asset->asset_type,
                'make' => $asset->make,
                'model' => $asset->model,
                'year' => $asset->year,
                'license_plate' => $asset->license_plate,
                'vin' => $asset->vin,
                'status' => $asset->status,

                'tracker' => $tracker ? [
                    'id' => $tracker->id,
                    'serial_number' => $tracker->serial_number,
                    'imei' => $tracker->imei,
                    'status' => $tracker->status,
                ] : null,

                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,

                'driver' => $asset->driver ? [
                    'id' => $asset->driver->id,
                ] : null,

                'organization' => $asset->organization ? [
                    'id' => $asset->organization->id,
                ] : null,

                'created_at' => $asset->created_at,
            ];
        });

        return $assets;
    }
}
