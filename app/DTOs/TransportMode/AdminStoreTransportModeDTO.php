<?php

namespace App\DTOs\TransportMode;

use Illuminate\Http\UploadedFile;

class AdminStoreTransportModeDTO
{
    public ?string $driver_id;
    public string $type;
    public string $category;
    public ?string $manufacturer;
    public ?string $model;
    public string $registration_number;
    public ?int $year_of_manufacture;
    public ?string $color;
    public ?int $passenger_capacity;
    public ?float $max_weight_capacity;
    public ?UploadedFile $photo;
    public ?UploadedFile $registration_document;

    public function __construct(array $validated)
    {
        $this->driver_id = $validated['driver_id'] ?? null;
        $this->type = $validated['type'];
        $this->category = $validated['category'];
        $this->manufacturer = $validated['manufacturer'] ?? null;
        $this->model = $validated['model'] ?? null;
        $this->registration_number = $validated['registration_number'];
        $this->year_of_manufacture = $validated['year_of_manufacture'] ?? null;
        $this->color = $validated['color'] ?? null;
        $this->passenger_capacity = $validated['passenger_capacity'] ?? null;
        $this->max_weight_capacity = $validated['max_weight_capacity'] ?? null;
        $this->photo = $validated['photo'] ?? null;
        $this->registration_document = $validated['registration_document'] ?? null;
    }
}
