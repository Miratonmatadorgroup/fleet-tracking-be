<?php

namespace App\DTOs\Partner;


use App\Models\User;
use Illuminate\Http\UploadedFile;
use App\Enums\TransportModeCategoryEnums;

class PartnerApplicationDTO
{
    public array $partnerInfo;
    public array $transportInfo;
    public array $driverInfo;
    public ?string $partner_bank_code;
    public ?string $partner_account_number;

    public function __construct(User $partner, array $partnerInfo, array $transportInfo, array $driverInfo)
    {
        $this->partnerInfo = [
            'full_name'       => $partnerInfo['full_name'] ?? $partner->name,
            'email'           => $partnerInfo['email'] ?? $partner->email,
            'phone'           => $partnerInfo['phone'] ?? $partner->phone,
            'whatsapp_number' => $partnerInfo['whatsapp_number'] ?? $partner->whatsapp_number,
            'business_name'   => $partnerInfo['business_name'] ?? null,
            'address'         => $partnerInfo['address'] ?? null,
            'bank_name'       => $partnerInfo['bank_name'] ?? null,
            'account_name'    => $partnerInfo['account_name'] ?? null,
            'account_number'  => $partnerInfo['account_number'] ?? null,
        ];

        $this->transportInfo = [
            'type'                  => $transportInfo['type'] ?? null,
            'category'              => TransportModeCategoryEnums::tryFrom($transportInfo['category'])?->value ?? null,
            'manufacturer'          => $transportInfo['manufacturer'] ?? null,
            'model'                 => $transportInfo['model'] ?? null,
            'registration_number'   => $transportInfo['registration_number'] ?? null,
            'year_of_manufacture'   => $transportInfo['year_of_manufacture'] ?? null,
            'color'                 => $transportInfo['color'] ?? null,
            'passenger_capacity'    => $transportInfo['passenger_capacity'] ?? null,
            'max_weight_capacity'   => $transportInfo['max_weight_capacity'] ?? null,
            'image'                 => isset($transportInfo['image']) && $transportInfo['image'] instanceof UploadedFile ? $transportInfo['image'] : null,
            'registration_document' => isset($transportInfo['registration_document']) && $transportInfo['registration_document'] instanceof UploadedFile ? $transportInfo['registration_document'] : null,
        ];

        //Only identifier needed for driver
        $this->driverInfo = [
            'identifier' => $driverInfo['identifier'] ?? null,
        ];

        $this->partner_bank_code      = $partnerInfo['bank_code'] ?? null;
        $this->partner_account_number = $partnerInfo['account_number'] ?? null;
    }
}
