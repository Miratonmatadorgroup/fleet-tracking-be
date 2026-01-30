<?php
namespace App\Actions\Partner;

use App\Models\Partner;
use App\Models\TransportMode;
use Illuminate\Support\Facades\Storage;
use App\DTOs\Partner\PartnerTransportModesDTO;
use App\Events\Partner\PartnerTransportModesRetrieved;

class GetPartnerTransportModesAction
{
    public function execute(Partner $partner, int $perPage = 10, ?string $search = null): PartnerTransportModesDTO
    {
        // pass $search to query
        $transportModes = $this->getTransportModesForPartner($partner, $perPage, $search);
        $transformedTransportModes = $this->transformTransportModes($transportModes);

        event(new PartnerTransportModesRetrieved($partner, $transformedTransportModes));

        return new PartnerTransportModesDTO(
            totalTransportModes: $transportModes->total(),
            transportModes: $transformedTransportModes,
            pagination: [
                'current_page' => $transportModes->currentPage(),
                'last_page'    => $transportModes->lastPage(),
                'per_page'     => $transportModes->perPage(),
                'total'        => $transportModes->total(),
            ]
        );
    }

    private function getTransportModesForPartner(Partner $partner, int $perPage, ?string $search = null)
    {
        $query = TransportMode::where('partner_id', $partner->id)
            ->with('driver')
            ->latest();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $likeOperator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';

                $q->where('manufacturer', $likeOperator, "%{$search}%")
                  ->orWhere('model', $likeOperator, "%{$search}%")
                  ->orWhere('registration_number', $likeOperator, "%{$search}%")
                  ->orWhere('color', $likeOperator, "%{$search}%")
                  ->orWhere('type', $likeOperator, "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    private function transformTransportModes($transportModes): array
    {
        return $transportModes->map(function ($transport) {
            return [
                'id'                  => $transport->id,
                'type'                => $transport->type->value,
                'manufacturer'        => $transport->manufacturer,
                'model'               => $transport->model,
                'registration_number' => $transport->registration_number,
                'color'               => $transport->color,
                'photo_path'          => $transport->photo_path ? Storage::url($transport->photo_path) : null,
                'driver'              => $transport->driver ? [
                    'id'     => $transport->driver->id,
                    'name'   => $transport->driver->name,
                    'phone'  => $transport->driver->phone,
                    'status' => $transport->driver->status->value,
                ] : null,
            ];
        })->toArray();
    }
}


