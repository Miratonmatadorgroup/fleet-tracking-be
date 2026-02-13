<?php

namespace App\Actions\Payment;

use App\Enums\PaymentStatusEnums;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\NotificationService;
use App\Services\Payments\ShanonoPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaySubscriptionWithGatewayAction
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function execute(Request $request)
    {
        $user = Auth::user();

        $planId = $request->input('subscription_plan_id');

        $plan = SubscriptionPlan::where('id', $planId)
            ->where('is_active', true)
            ->firstOrFail();

        $activeSubscription = Subscription::where('user_id', $user->id)
            ->where('end_date', '>=', now())
            ->active()
            ->first();

        if ($activeSubscription) {
            throw new \Exception("You already have an active subscription.");
        }

        $service = app(ShanonoPayService::class);

        $paymentData = $service->initiateSubscription($user, $plan);
        Log::info($paymentData);

        if (!($paymentData['status'] ?? false)) {
            throw new \Exception($paymentData['message'] ?? 'Payment initialization failed.');
        }

        $reference = $paymentData['reference'];


        Payment::create([
            'user_id'   => $user->id,
            'amount'    => $plan->price,
            'status'    => PaymentStatusEnums::PENDING,
            'gateway'   => 'shanono',
            'reference' => $reference,
            'meta'      => [
                'subscription_plan_id' => $plan->id
            ],
        ]);

        return successResponse('Payment initialized.', [
            'payment_url' => $paymentData['checkout_url'],
            'verify_url'  => data_get($paymentData, 'verify_url'),
            'reference'   => $reference,
        ]);
    }
}
