<?php

namespace App\DTOs\Partner;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use App\Enums\TransportModeCategoryEnums;

class AddFleetMemberDTO
{
    public function __construct(
        public readonly array $transportInfo,
        public readonly array $driverInfo
    ) {}

    public static function fromRequest(User $partner, array $transportInfo, array $driverInfo): self
    {
        return new self(
            transportInfo: [
                'type'                  => $transportInfo['type'] ?? null,
                'category'              => TransportModeCategoryEnums::tryFrom($transportInfo['category'])?->value ?? null,
                'manufacturer'          => $transportInfo['manufacturer'] ?? null,
                'model'                 => $transportInfo['model'] ?? null,
                'registration_number'   => $transportInfo['registration_number'] ?? null,
                'year_of_manufacture'   => $transportInfo['year_of_manufacture'] ?? null,
                'color'                 => $transportInfo['color'] ?? null,
                'passenger_capacity'    => $transportInfo['passenger_capacity'] ?? null,
                'max_weight_capacity'   => $transportInfo['max_weight_capacity'] ?? null,
                'image'                 => $transportInfo['image'] ?? null,
                'registration_document' => $transportInfo['registration_document'] ?? null,
            ],
            driverInfo: [
                'identifier' => $driverInfo['identifier'] ?? null,
            ]
        );
    }
}
