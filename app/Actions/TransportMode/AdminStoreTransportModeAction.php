<?php
namespace App\Actions\TransportMode;

use App\Models\TransportMode;
use Illuminate\Support\Facades\Storage;
use App\DTOs\TransportMode\AdminStoreTransportModeDTO;


class AdminStoreTransportModeAction
{
    public function execute(AdminStoreTransportModeDTO $dto): TransportMode
    {
        $photoPath = $dto->photo
            ? $dto->photo->store('transport_photos', 'public')
            : null;

        $documentPath = $dto->registration_document
            ? $dto->registration_document->store('transport_documents', 'public')
            : null;

        $transport = TransportMode::create([
            'driver_id' => $dto->driver_id,
            'type' => $dto->type,
            'category' => $dto->category,
            'manufacturer' => $dto->manufacturer,
            'model' => $dto->model,
            'registration_number' => $dto->registration_number,
            'year_of_manufacture' => $dto->year_of_manufacture,
            'color' => $dto->color,
            'passenger_capacity' => $dto->passenger_capacity,
            'max_weight_capacity' => $dto->max_weight_capacity,
            'photo_path' => $photoPath,
            'registration_document' => $documentPath,
        ]);


        return $transport;
    }
}
