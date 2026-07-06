<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatusEnums;
use App\Enums\SubscriptionStatusEnums;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Payments\ShanonoPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    public function initiate(Request $request, ShanonoPayService $shanono)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $user = Auth::user();

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        DB::beginTransaction();

        try {

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,

                // optional if nullable
                'asset_id' => $request->asset_id,

                'start_date' => now(),
                'end_date' => now()->addMonth(),

                // FIXED HERE
                'status' => SubscriptionStatusEnums::PENDING,

                'payment_method' => 'shanono',

                'auto_renew' => true,
                'is_trial' => false,
            ]);

            $paymentData = $shanono->initiateSubscriptionPayment(
                $subscription,
                $plan,
                $user
            );

            $reference = $paymentData['reference'] ?? null;

            if (!$reference) {

                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Missing payment reference',
                    'data' => $paymentData
                ], 422);
            }

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'reference' => $reference,
                'transaction_id' => $paymentData['payment_id'] ?? null,

                'amount' => $plan->price,
                'currency' => 'NGN',
                'status' => PaymentStatusEnums::PENDING,
                'gateway' => 'shanono',

                'meta' => [
                    'gateway_reference' => $paymentData['gateway_reference'] ?? null,
                    'payment_id' => $paymentData['payment_id'] ?? null,
                    'raw' => $paymentData['raw'] ?? null,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subscription payment initiated',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'payment_reference' => $reference,
                    'payment' =>
                    $paymentData['payment_link'] ?? null,

                    'callback_url' =>
                    $paymentData['callback_url'] ?? null,

                    'webhook_url' =>
                    $paymentData['webhook_url'] ?? null,

                    'raw' =>
                    $paymentData['raw'] ?? null,

                    'payment_id' =>
                    $paymentData['payment_id'] ?? null,
                    'verify_url' => route(
                        'subscriptions.payments.verify',
                        [
                            'reference' => $reference,
                            'subscription_id' => $subscription->id,
                        ]
                    ),
                ]
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Subscription initiation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize subscription payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function redirectHandler(Request $request)
    {
        $reference = $request->query('reference');
        $subscriptionId = $request->query('subscription_id');

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return redirect()->away(
            "{$frontendUrl}/payments/confirm?reference={$reference}&subscription_id={$subscriptionId}"
        );
    }

    public function webhookHandler(Request $request, ShanonoPayService $shanono)
    {
        Log::info('Subscription webhook payload', $request->all());

        return $this->verify($request, $shanono);
    }

    public function verify(Request $request, ShanonoPayService $shanono)
    {
        $reference = $request->query('reference');
        $subscriptionId = $request->query('subscription_id');
        Log::info('Subscription verify called', [
            'reference' => $reference,
            'subscription_id' => $subscriptionId,
        ]);

        $payment = Payment::where('reference', $reference)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        // ALWAYS use gateway reference first
        $gatewayRef = $payment->transaction_id
            ?? data_get($payment->meta, 'payment_id')
            ?? data_get($payment->meta, 'gateway_reference')
            ?? $payment->reference;

        $verification =
            $shanono->verifySubscriptionPayment(
                $gatewayRef,
                $subscriptionId
            );
        if ($verification['pending'] ?? false) {
            return response()->json([
                'success' => true,
                'message' => 'Payment still processing',
                'status' => 'pending',
                'data' => $verification
            ], 202);
        }

        if (!($verification['status'] ?? false)) {

            $payment->update([
                'status' => PaymentStatusEnums::FAILED
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'data' => $verification
            ], 400);
        }

        $subscription = Subscription::find($subscriptionId);


        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        $gatewayData = $verification['data'] ?? [];
        $processorRef = $gatewayData['processor_ref'] ?? null;

        DB::transaction(function () use (
            $subscription,
            $payment,
            $processorRef,
            $gatewayData
        ) {
            $subscription->update([
                'status' => SubscriptionStatusEnums::ACTIVE,
                'price_per_month' => $subscription->plan->price,
                'start_date' => now(),
                'end_date' => now()->addMonth(),
            ]);

            $payment->update([
                'status' => PaymentStatusEnums::PAID,
                'transaction_id' => $processorRef,
                'gateway_response' => $gatewayData,
                'meta' => array_merge($payment->meta ?? [], [
                    'processor_ref' => $processorRef,
                ]),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully',
            'data' => [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'expires_at' => $subscription->end_date,
            ]
        ]);
    }
}
