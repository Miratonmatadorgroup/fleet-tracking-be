<?php

namespace App\Actions\Partner;


use App\Models\User;
use App\Models\Driver;
use Illuminate\Support\Str;
use App\Models\TransportMode;
use Illuminate\Support\Carbon;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Services\WalletService;
use App\Enums\DriverStatusEnums;
use App\Services\SmileIdService;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use App\DTOs\Partner\AddFleetMemberDTO;
use App\Events\Partner\FleetMemberAdded;
use App\Enums\DriverApplicationStatusEnums;
use App\DTOs\Authentication\RegisterUserDTO;
use Illuminate\Validation\ValidationException;
use App\Actions\Authentication\RegisterUserAction;


//NORMAL FLOW WITHOUT SMILEID
class AddFleetMemberAction
{
    public function __construct(
        protected RegisterUserAction $registerUserAction,
        protected WalletService $walletService,
        protected TwilioService $twilio,
        protected TermiiService $termii
    ) {}

    public function execute(AddFleetMemberDTO $dto, User $partnerUser): array
    {
        return DB::transaction(function () use ($dto, $partnerUser) {
            $partner = $partnerUser->partner;

            if (!$partner) {
                throw new \Exception('Partner record not found.');
            }

            //Get existing driver by identifier
            $identifier = $dto->driverInfo['identifier'];
            $driverUser = User::where('email', $identifier)
                ->orWhere('phone', $identifier)
                ->orWhere('whatsapp_number', $identifier)
                ->first();

            if (!$driverUser) {
                throw ValidationException::withMessages([
                    'driver_identifier' => ['The provided driver identifier does not belong to any registered user.']
                ]);
            }

            $driver = Driver::where('user_id', $driverUser->id)->first();
            if (!$driver) {
                throw ValidationException::withMessages([
                    'driver_identifier' => ['The provided driver has not yet applied or been approved as a driver.']
                ]);
            }

            //Check if driver already linked to any transport mode
            $existingTransport = TransportMode::where('driver_id', $driver->id)->first();
            if ($existingTransport) {
                $existingPartner = $existingTransport->partner?->user?->name ?? 'another partner';
                throw ValidationException::withMessages([
                    'driver_identifier' => [
                        "This driver is already assigned to a transport mode under {$existingPartner}."
                    ]
                ]);
            }

            //Check if driver already belongs to another partner (redundant but safe)
            $existingPartnerLink = TransportMode::where('partner_id', '!=', $partner->id)
                ->where('driver_id', $driver->id)
                ->first();

            if ($existingPartnerLink) {
                throw ValidationException::withMessages([
                    'driver_identifier' => ['This driver already belongs to another partner.']
                ]);
            }

            //Upload transport files
            $photoPath = $dto->transportInfo['image']?->store('transport_photos', 'public');
            $documentPath = $dto->transportInfo['registration_document']?->store('transport_documents', 'public');

            //Create new TransportMode record
            $transport = TransportMode::create([
                'driver_id'             => $driver->id,
                'partner_id'            => $partner->id,
                'type'                  => $dto->transportInfo['type'],
                'category'              => $dto->transportInfo['category'],
                'manufacturer'          => $dto->transportInfo['manufacturer'],
                'model'                 => $dto->transportInfo['model'],
                'registration_number'   => $dto->transportInfo['registration_number'],
                'year_of_manufacture'   => $dto->transportInfo['year_of_manufacture'],
                'color'                 => $dto->transportInfo['color'],
                'passenger_capacity'    => $dto->transportInfo['passenger_capacity'],
                'max_weight_capacity'   => $dto->transportInfo['max_weight_capacity'],
                'photo_path'            => $photoPath,
                'registration_document' => $documentPath,
                'approval_status'       => 'pending',
            ]);

            //Fire event for notifications / logging
            FleetMemberAdded::dispatch($partner, $driver, $transport, $partnerUser);

            return [
                'driver'    => $driver,
                'transport' => $transport,
            ];
        });
    }
}

