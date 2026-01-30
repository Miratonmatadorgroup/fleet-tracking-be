<?php

namespace App\Actions\Partner;


use Exception;
use App\Models\User;
use App\Models\Driver;
use Illuminate\Support\Str;
use App\Models\TransportMode;
use Illuminate\Support\Carbon;
use App\Services\NubapiService;
use App\Services\WalletService;
use App\Enums\DriverStatusEnums;
use App\Enums\PartnerStatusEnums;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BankAccountNameResolver;
use App\DTOs\Partner\PartnerApplicationDTO;
use App\Enums\DriverApplicationStatusEnums;
use App\DTOs\Authentication\RegisterUserDTO;
use App\Enums\PartnerApplicationStatusEnums;
use Illuminate\Validation\ValidationException;
use App\Actions\Authentication\RegisterUserAction;
use App\Events\Partner\PartnerApplicationSubmitted;

class PartnerApplicationAction
{
    public function __construct(
        protected RegisterUserAction $registerUserAction,
        protected WalletService $walletService,
        protected BankAccountNameResolver $bankAccountNameResolver,
    ) {}


    public function execute(PartnerApplicationDTO $dto, User $user): array
    {
        return DB::transaction(function () use ($dto, $user) {

            // Verify partner bank
            $partnerBank = $this->resolvePartnerBank($dto, $user);

            // Create or update partner record
            $partner = $user->partner;
            if (!$partner) {
                $partner = $user->partner()->create([
                    'full_name'          => $dto->partnerInfo['full_name'],
                    'email'              => $dto->partnerInfo['email'],
                    'phone'              => $dto->partnerInfo['phone'],
                    'whatsapp_number'    => $dto->partnerInfo['whatsapp_number'],
                    'business_name'      => $dto->partnerInfo['business_name'],
                    'address'            => $dto->partnerInfo['address'],
                    'bank_name'          => $dto->partnerInfo['bank_name'],
                    'bank_code'          => $partnerBank['bank_code'],
                    'account_number'     => $dto->partnerInfo['account_number'],
                    'account_name'       => $partnerBank['account_name'],
                    'status'             => PartnerStatusEnums::INACTIVE,
                    'application_status' => PartnerApplicationStatusEnums::REVIEW,
                ]);
            } else {
                $partner->update([
                    'application_status' => PartnerApplicationStatusEnums::REVIEW,
                    'bank_name'          => $dto->partnerInfo['bank_name'],
                    'bank_code'          => $partnerBank['bank_code'],
                    'account_number'     => $dto->partnerInfo['account_number'],
                    'account_name'       => $partnerBank['account_name'],
                ]);
            }

            //Get existing driver user by identifier
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
                    'driver_identifier' => ['The provided driver user has not yet applied to be a driver.']
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

            //Check if driver already belongs to another partner (extra safety)
            $existingPartnerLink = TransportMode::where('partner_id', '!=', $partner->id)
                ->where('driver_id', $driver->id)
                ->first();

            if ($existingPartnerLink) {
                throw ValidationException::withMessages([
                    'driver_identifier' => ['This driver already belongs to another partner.']
                ]);
            }

            // Upload transport files
            $photoPath = $dto->transportInfo['image']?->store('transport_photos', 'public');
            $documentPath = $dto->transportInfo['registration_document']?->store('transport_documents', 'public');

            // Create transport mode linked to driver + partner
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
            ]);

            PartnerApplicationSubmitted::dispatch($partner, $driver, $transport);

            return [
                'partner'   => $partner,
                'driver'    => $driver,
                'transport' => $transport,
            ];
        });
    }

    /**
     * Verify Partner's Bank Account
     */

    private function resolvePartnerBank(PartnerApplicationDTO $dto, User $user): array
    {
        $result = $this->bankAccountNameResolver->resolve(
            $dto->partner_account_number,
            $dto->partner_bank_code
        );

        if (! $result['success'] || empty($result['account_name'])) {
            throw new Exception(
                "Unable to verify partner bank account. " . ($result['error'] ?? '')
            );
        }

        // $accountName = $result['account_name'];

        // if (! $this->namesLooselyMatch($user->name, $accountName)) {
        //     throw new Exception(
        //         "Partner profile name does not reasonably match bank account name. " .
        //             "Profile: {$user->name}, Bank: {$accountName}"
        //     );
        // }

        return [
            'account_name'   => $result['account_name'],
            'account_number' => $dto->partner_account_number,
            'bank_name'      => $dto->partnerInfo['bank_name'] ?? null,
            'bank_code'      => $dto->partner_bank_code,
        ];
    }

    private function namesLooselyMatch(string $profileName, string $bankName): bool
    {
        $normalize = function ($name) {
            $name = strtoupper($name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = preg_replace('/[^A-Z\s]/', '', $name);
            return trim($name);
        };

        $profile = $normalize($profileName);
        $bank    = $normalize($bankName);

        $profileParts = explode(' ', $profile);
        $bankParts    = explode(' ', $bank);

        // Require at least FIRST + LAST name overlap
        return count(array_intersect($profileParts, $bankParts)) >= 2;
    }
}
