<?php

namespace App\Actions\Investor;

use Exception;
use App\Models\User;
use App\Models\Investor;
use App\Models\InvestmentPlan;
use App\Services\NubapiService;
use App\Services\PaystackService;
use App\Enums\InvestorStatusEnums;
use Illuminate\Support\Facades\Auth;
use App\Services\BankAccountNameResolver;
use App\DTOs\Investor\InvestorApplicationDTO;
use App\Enums\InvestorApplicationStatusEnums;
use App\Events\Investor\InvestorApplicationSubmitted;

class StoreInvestorApplicationAction
{
    public function __construct(
        protected BankAccountNameResolver $bankAccountNameResolver
    ) {}
    public function execute(InvestorApplicationDTO $dto): Investor
    {
        $user = Auth::user();
        //bank account with Nubapi
        $bank = $this->resolveInvestorBank($dto, $user);

        $plan = InvestmentPlan::findOrFail($dto->investment_plan_id);


        $application = Investor::create([
            'user_id'            => $user->id,
            'full_name'          => $dto->full_name,
            'email'              => $dto->email,
            'phone'              => $dto->phone,
            'whatsapp_number'    => $dto->whatsapp_number,
            'business_name'      => $dto->business_name,
            'address'            => $dto->address,
            'gender'             => $dto->gender,
            'bank_name'          => $bank['bank_name'],
            'bank_code'          => $bank['bank_code'],
            'account_name'       => $bank['account_name'],
            'account_number'     => $bank['account_number'],
            'next_of_kin_name'   => $dto->next_of_kin_name,
            'next_of_kin_phone'  => $dto->next_of_kin_phone,
            'investment_amount'  => $plan->amount,
            'payment_method'     => $dto->payment_method?->value,
            'status'             => InvestorStatusEnums::INACTIVE,
            'application_status' => InvestorApplicationStatusEnums::REVIEW,
        ]);
        

        event(new InvestorApplicationSubmitted($application));

        return $application;
    }

    private function resolveInvestorBank(InvestorApplicationDTO $dto, User $user): array {
        $result = $this->bankAccountNameResolver->resolve(
            accountNumber: $dto->account_number,
            bankCode: $dto->bank_code
        );

        if (! $result['success'] || empty($result['account_name'])) {
            throw new Exception(
                "Unable to verify bank account. " . ($result['error'] ?? '')
            );
        }

        // $accountName = $result['account_name'];

        // if (! $this->namesLooselyMatch($user->name, $accountName)) {
        //     throw new Exception(
        //         "Investor profile name does not reasonably match bank account name. " .
        //             "Profile: {$user->name}, Bank: {$accountName}"
        //     );
        // }

        return [
            'account_name'   => $result['account_name'],
            'account_number' => $dto->account_number,
            'bank_name'      => $dto->bank_name,
            'bank_code'      => $dto->bank_code,
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
