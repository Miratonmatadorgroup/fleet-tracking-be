<?php

namespace App\Actions\Partner;


use App\Models\Partner;
use App\Enums\PartnerStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Enums\PartnerApplicationStatusEnums;
use App\DTOs\Partner\ReviewPartnerApplicationDTO;
use App\Events\Partner\PartnerApplicationReviewedEvent;

class ReviewPartnerApplicationAction
{
    public function execute(ReviewPartnerApplicationDTO $dto): Partner
    {
        return DB::transaction(function () use ($dto) {
            $partner = Partner::with('transportModes.driver.user')->findOrFail($dto->partnerId);

            if (in_array($partner->application_status, [
                PartnerApplicationStatusEnums::APPROVED,
                PartnerApplicationStatusEnums::REJECTED
            ])) {
                throw new \Exception("This partner's application has already been {$partner->application_status->value} and cannot be modified.");
            }

            $partner->application_status = $dto->applicationStatus;

            if ($dto->applicationStatus === PartnerApplicationStatusEnums::APPROVED) {
                $partner->status = PartnerStatusEnums::ACTIVE;

                $user = $partner->user;
                if ($user && !$user->hasRole('partner')) {
                    $user->assignRole('partner');
                }

                foreach ($partner->transportModes as $transportMode) {
                    $driver = $transportMode->driver;

                    if ($driver && $driver->user && !$driver->user->hasRole('driver')) {
                        $driver->user->assignRole('driver');
                    }
                }
            }

            $partner->save();

            event(new PartnerApplicationReviewedEvent(
                partner: $partner,
                approved: $dto->applicationStatus === PartnerApplicationStatusEnums::APPROVED
            ));

            return $partner;
        });
    }
}
