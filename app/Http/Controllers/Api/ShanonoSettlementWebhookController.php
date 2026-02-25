<?php

namespace App\Http\Controllers\Api;

use App\Models\Payout;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Enums\PayoutStatusEnums;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Notifications\User\PayoutFailedNotification;
use App\Notifications\User\PayoutCompletedNotification;
use Illuminate\Support\Facades\Http;


class ShanonoSettlementWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Shanono webhook hit', [
            'payload' => $request->getContent()
        ]);
        Log::info($request->headers);

        try {

            $this->verifySignature($request);

            Log::info('Shanono webhook signature verified');

            $data = $request->all();
            $payload = $data['data'] ?? $data;

            $isSuccessful =
                ($payload['status'] ?? null) === 'success'
                || ($payload['event'] ?? null) === 'payment.received';

            $subAccount =
                $payload['account_number']
                ?? $payload['sub_account_number']
                ?? null;

            if ($isSuccessful && $subAccount && isset($payload['reference'])) {

                $reference = $payload['reference'];
                $amount    = (float) ($payload['amount'] ?? 0);

                // Check if reference belongs to payout
                $payout = Payout::where('provider_reference', $reference)->exists();

                if ($payout) {

                    $this->handlePayoutSettlement([
                        'reference' => $reference,
                        'amount'    => $amount,
                        'status'    => 'success',
                    ]);
                } else {

                    $this->handleWalletSettlement([
                        'sub_account_number' => $subAccount,
                        'amount'             => $amount,
                        'reference'          => $reference,
                        'status'             => 'success',
                        'meta'               => $payload,
                    ]);
                }
            } else {

                Log::warning('Unhandled Shanono webhook payload', $payload);
            }
        } catch (\Throwable $e) {

            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook'
            ], 400);
        }

        return response()->json(['status' => 'success'], 200);
    }
    protected function handleWalletSettlement(array $data)
    {
        if (($data['status'] ?? null) !== 'success') {
            return;
        }

        DB::transaction(function () use ($data) {
            Log::info('Looking up wallet', [
                'sub_account_number' => $data['sub_account_number'] ?? null,
            ]);


            $wallet = Wallet::query()
                ->where('external_account_number', trim((string) $data['sub_account_number']))
                ->lockForUpdate()
                ->first();


            if (! $wallet) {
                Log::error('Wallet not found for sub-account transfer', [
                    'sub_account_number' => $data['sub_account_number'],
                ]);
                return;
            }

            if (
                WalletTransaction::where('provider', 'shanono')
                ->where('provider_ref', $data['reference'])
                ->exists()

            ) {
                Log::warning('Duplicate Shanono webhook ignored', [
                    'reference' => $data['reference']
                ]);
                return;
            }

            $amount = (float) $data['amount'];

            Log::info('Incoming amount debug', [
                'raw_amount' => $data['amount'],
                'type' => gettype($data['amount']),
            ]);


            Log::info('Before wallet increment', [
                'wallet_id' => $wallet->id,
                'available_balance' => $wallet->available_balance,
                'total_balance' => $wallet->total_balance,
                'incoming_amount' => $data['amount'],
            ]);

            //Increment balances
            $wallet->available_balance = bcadd($wallet->available_balance, $data['amount'], 2);
            $wallet->total_balance = bcadd($wallet->total_balance, $data['amount'], 2);


            $wallet->save();

            // Debug: After increment
            Log::info('After wallet increment', [
                'wallet_id' => $wallet->id,
                'new_available_balance' => $wallet->available_balance,
                'new_total_balance' => $wallet->total_balance,
            ]);

            WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'user_id'      => $wallet->user_id,
                'reference'    => $data['reference'],
                'amount'       => $amount,
                'type'         => 'credit',
                'status'       => 'success',
                'provider'     => 'shanono',
                'provider_ref' => $data['reference'],
                'meta'         => $data,
            ]);
        });
    }

    protected function handlePayoutSettlement(array $data)
    {
        DB::transaction(function () use ($data) {

            $payout = Payout::where(
                'provider_reference',
                $data['reference']
            )->lockForUpdate()->first();

            if (! $payout) {
                Log::error('Payout not found for webhook', $data);
                return;
            }

            if (in_array($payout->status, [
                PayoutStatusEnums::COMPLETED,
                PayoutStatusEnums::FAILED
            ])) {
                return;
            }

            if (($data['status'] ?? null) === 'success') {
                $payout->update([
                    'status' => PayoutStatusEnums::COMPLETED,
                ]);

                $payout->user?->notify(
                    new PayoutCompletedNotification($payout)
                );

                return;
            }

            if (($data['status'] ?? null) === 'failed') {

                //Idempotency check (VERY IMPORTANT)
                if (
                    WalletTransaction::where('provider', 'shanono')
                    ->where('provider_ref', $data['reference'])
                    ->exists()

                ) {
                    return;
                }

                $payout->update([
                    'status' => PayoutStatusEnums::FAILED,
                ]);

                $wallet = Wallet::where('user_id', $payout->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    //Normalize amount
                    $amount = (float) $data['amount'];
                    // $amount = bcdiv($data['amount'], '100', 2);

                    Log::info('Incoming amount debug', [
                        'raw_amount' => $data['amount'],
                        'type' => gettype($data['amount']),
                    ]);


                    Log::info('Before wallet increment', [
                        'wallet_id' => $wallet->id,
                        'available_balance' => $wallet->available_balance,
                        'total_balance' => $wallet->total_balance,
                        'incoming_amount' => $data['amount'],
                    ]);

                    // Increment balances
                    $wallet->available_balance = bcadd($wallet->available_balance, $data['amount'], 2);
                    $wallet->total_balance = bcadd($wallet->total_balance, $data['amount'], 2);

                    $wallet->save();

                    // Debug: After increment
                    Log::info('After wallet increment', [
                        'wallet_id' => $wallet->id,
                        'new_available_balance' => $wallet->available_balance,
                        'new_total_balance' => $wallet->total_balance,
                    ]);

                    WalletTransaction::create([
                        'wallet_id'    => $wallet->id,
                        'user_id'      => $payout->user_id,
                        'reference'    => $data['reference'],
                        'amount'       => $amount,
                        'type'         => 'credit',
                        'status'       => 'success',
                        'provider'     => 'shanono',
                        'provider_ref' => $data['reference'],
                        'meta'         => $data,
                    ]);
                }

                $payout->user?->notify(
                    new PayoutFailedNotification($payout)
                );
            }
        });

        return;
    }
    protected function verifySignature(Request $request): void
    {
        $signature = $request->headers->get('X-signature')
            ?? $request->headers->get('X-Signature');


        $payload = file_get_contents('php://input');

        $secret = app()->environment('production')
            ? config('services.shanono_bank.webhook_secret_production')
            : config('services.shanono_bank.webhook_secret_staging');

        $expected = hash_hmac('sha256', $payload, $secret);

        Log::info('Webhook signature debug', [
            'payload'  => $payload,
            'expected' => $expected,
            'received' => $signature,
            'env' => app()->environment(),
        ]);

        if (! hash_equals(strtolower($expected), strtolower($signature))) {
            throw new \Exception('Invalid webhook signature');
        }
    }
}
