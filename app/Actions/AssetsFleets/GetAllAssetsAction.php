<?php

namespace App\Actions\AssetsFleets;

use App\Models\Asset;
use Illuminate\Support\Str;

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

            $search = Str::lower(trim($search));

            $dbDriver = $query->getConnection()->getDriverName();
            $likeOperator = $dbDriver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $dbDriver, $likeOperator) {

                // Asset ID
                if ($dbDriver === 'pgsql') {
                    $q->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                } else {
                    $q->where('id', $likeOperator, "%{$search}%");
                }

                // Asset fields
                $q->orWhereRaw("LOWER(asset_type) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(make) {$likeOperator} ?", ["%{$search}%"])
                    ->orWhereRaw("LOWER(model) {$likeOperator} ?", ["%{$search}%"]);

                // Year
                if ($dbDriver === 'pgsql') {
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

                    ->orWhereHas('driver', function ($driverQuery) use ($search, $dbDriver, $likeOperator) {
                        if ($dbDriver === 'pgsql') {
                            $driverQuery->whereRaw("CAST(id AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                        } else {
                            $driverQuery->where('id', $likeOperator, "%{$search}%");
                        }
                    })

                    ->orWhereHas('organization', function ($orgQuery) use ($search, $dbDriver, $likeOperator) {
                        if ($dbDriver === 'pgsql') {
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
}
