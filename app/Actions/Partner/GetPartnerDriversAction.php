<?php

namespace App\Actions\Partner;

use App\Models\Driver;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use App\DTOs\Partner\PartnerDriversDTO;
use Illuminate\Support\Facades\Storage;
use App\Events\Partner\PartnerDriversRetrieved;

class GetPartnerDriversAction
{
    public function execute(Partner $partner, int $perPage = 10, ?string $search = null): PartnerDriversDTO
    {
        $drivers = $this->getDriversForPartner($partner, $perPage, $search);
        $transformedDrivers = $this->transformDrivers($drivers);

        event(new PartnerDriversRetrieved($partner, $transformedDrivers));

        return new PartnerDriversDTO(
            totalDrivers: $drivers->total(),
            drivers: $transformedDrivers,
            pagination: [
                'current_page' => $drivers->currentPage(),
                'last_page'    => $drivers->lastPage(),
                'per_page'     => $drivers->perPage(),
                'total'        => $drivers->total(),
            ]
        );
    }

    private function getDriversForPartner(Partner $partner, int $perPage, ?string $search = null)
    {
        $driver = DB::getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query = Driver::whereHas('transportModeDetails', function ($query) use ($partner) {
            $query->where('partner_id', $partner->id);
        })
            ->with(['transportModeDetails' => function ($query) use ($partner) {
                $query->where('partner_id', $partner->id);
            }])
            ->latest();

        if (!empty($search)) {
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('name', $likeOperator, "%{$search}%")
                    ->orWhere('email', $likeOperator, "%{$search}%")
                    ->orWhere('phone', $likeOperator, "%{$search}%")
                    ->orWhereHas('transportModeDetails', function ($tq) use ($search, $likeOperator) {
                        $tq->where('model', $likeOperator, "%{$search}%")
                            ->orWhere('registration_number', $likeOperator, "%{$search}%");
                    });
            });
        }

        return $query->paginate($perPage);
    }

    private function transformDrivers($drivers): array
    {
        return $drivers->map(function ($driver) {
            $driverArray = $driver->toArray(); // includes all appended attributes

            // Override or enhance transport mode details if needed
            if ($driver->transportModeDetails) {
                $mode = $driver->transportModeDetails;
                $driverArray['transport_mode'] = [
                    'type'                => $mode->type->value,
                    'model'               => $mode->model,
                    'registration_number' => $mode->registration_number,
                    'photo_path'          => $mode->photo_path ? Storage::url($mode->photo_path) : null,
                ];
            } else {
                $driverArray['transport_mode'] = null;
            }

            return $driverArray;
        })->toArray();
    }


    // private function transformDrivers($drivers): array
    // {
    //     return $drivers->map(function ($driver) {
    //         return [
    //             'id'                 => $driver->id,
    //             'name'               => $driver->name,
    //             'email'              => $driver->email,
    //             'phone'              => $driver->phone,
    //             'status'             => $driver->status->value,
    //             'application_status' => $driver->application_status->value,
    //             'profile_photo'      => $driver->profile_photo ? Storage::url($driver->profile_photo) : null,
    //             'transport_mode'     => $driver->transportModeDetails ? [
    //                 'type'                => $driver->transportModeDetails->type->value,
    //                 'model'               => $driver->transportModeDetails->model,
    //                 'registration_number' => $driver->transportModeDetails->registration_number,
    //                 'photo_path'          => $driver->transportModeDetails->photo_path ? Storage::url($driver->transportModeDetails->photo_path) : null,
    //             ] : null,
    //         ];
    //     })->toArray();
    // }
}
