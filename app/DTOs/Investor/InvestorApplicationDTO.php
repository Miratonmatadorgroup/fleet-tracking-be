<?php

namespace App\DTOs\Investor;

use App\Models\User;
use App\Models\InvestmentPlan;
use App\Enums\PaymentMethodEnums;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvestorApplicationDTO
{
    public string $full_name;
    public ?string $email;
    public ?string $phone;
    public ?string $whatsapp_number;

    public ?string $business_name;
    public ?string $address;
    public ?string $gender;
    public ?string $bank_name;
    public ?string $bank_code;
    public ?string $account_number;
    public ?string $next_of_kin_name;
    public ?string $next_of_kin_phone;
    public string $investment_plan_id;
    public float $investment_amount;
    public ?PaymentMethodEnums $payment_method;

    public function __construct(User $user, array $data)
    {
        $this->validate($data, $user);

        $this->full_name = $user->name;
        $this->email = $user->email ?? null;
        $this->phone = $user->phone ?? null;
        $this->whatsapp_number = $user->whatsapp_number ?? null;

        $this->business_name = $data['business_name'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->gender = $data['gender'] ?? null;
        $this->bank_name = $data['bank_name'] ?? null;
        $this->bank_code = $data['bank_code'] ?? null;
        $this->account_number = $data['account_number'] ?? null;
        $this->next_of_kin_name = $data['next_of_kin_name'] ?? null;
        $this->next_of_kin_phone = $data['next_of_kin_phone'] ?? null;

        $this->investment_plan_id = $data['investment_plan_id'];
        $plan = InvestmentPlan::findOrFail($this->investment_plan_id);
        $this->investment_amount = (float) $plan->amount;

        $this->payment_method = isset($data['payment_method'])
            ? PaymentMethodEnums::from($data['payment_method'])
            : null;
    }

    public static function fromRequest(array $data, User $user): self
    {
        return new self($user, $data);
    }

    private function validate(array $data, User $user): void
    {
        //Ensure at least one of email, phone, or WhatsApp is provided
        if (empty($user->email) && empty($user->phone) && empty($user->whatsapp_number)) {
            throw ValidationException::withMessages([
                'contact' => 'At least one contact method (email, phone, or WhatsApp number) is required.',
            ]);
        }

        $validator = Validator::make($data, [
            'business_name'       => 'nullable|string|max:255',
            'address'             => 'nullable|string',
            'gender'              => 'nullable|in:male,female,other',
            'bank_name'           => 'nullable|string|max:255',
            'bank_code'           => 'required|string|max:10',
            'account_name'        => 'nullable|string|max:255',
            'account_number'      => 'nullable|string|max:20',
            'next_of_kin_name'    => 'nullable|string|max:255',
            'next_of_kin_phone'   => 'nullable|string|max:20',
            'investment_plan_id' => 'required|exists:investment_plans,id',
            'payment_method'     => ['nullable', new Enum(PaymentMethodEnums::class)],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
