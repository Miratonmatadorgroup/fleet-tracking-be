<?php

namespace App\DTOs\Payment;


use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use App\Enums\PaymentStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Services\WalletGuardService;
use Illuminate\Support\Facades\Auth;
use App\Services\ExternalBankService;
use App\Services\NotificationService;
use App\Enums\SubscriptionStatusEnums;
use App\Services\TransactionPinService;
use App\Services\WalletPurchaseService;

class PaySubscriptionWithWalletDTO
{
    public string $subscription_plan_id;
    public string $pin;

    public static function fromRequest(Request $request): self
    {
        $request->validate([
            'subscription_plan_id' => 'required|uuid',
            'transaction_pin'      => 'required|size:4',
        ]);

        $dto = new self();
        $dto->subscription_plan_id = $request->subscription_plan_id;
        $dto->pin = $request->transaction_pin;

        return $dto;
    }
}
