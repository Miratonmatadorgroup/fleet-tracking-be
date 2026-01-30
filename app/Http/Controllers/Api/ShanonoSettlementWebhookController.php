<?php

namespace App\Http\Controllers\Api;

use App\Models\Payout;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\PayoutStatusEnums;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Notifications\User\PayoutFailedNotification;
use App\Notifications\User\PayoutCompletedNotification;

class ShanonoSettlementWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $this->verifySignature($request);

        $data = $request->input('data');

        if (isset($data['account_number'])) {
            return $this->handleWalletSettlement($data);
        }

        if (isset($data['reference'])) {
            return $this->handlePayoutSettlement($data);
        }

        Log::warning('Unknown Shanono webhook payload', $data);

        return response()->json(['status' => 'ignored']);
    }

    protected function handleWalletSettlement(array $data)
    {
        if (($data['status'] ?? null) !== 'successful') {
            return response()->json(['status' => 'ignored']);
        }

        DB::transaction(function () use ($data) {

            $wallet = Wallet::where(
                'external_account_number',
                $data['account_number']
            )->lockForUpdate()->first();

            if (! $wallet) {
                Log::error('Wallet not found for settlement', $data);
                return;
            }

            if (WalletTransaction::where(
                'reference',
                $data['reference']
            )->exists()) {
                return;
            }

            $wallet->increment('available_balance', $data['amount']);
            $wallet->increment('total_balance', $data['amount']);


            WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'user_id'      => $wallet->user_id,
                'reference'    => Str::uuid(),
                'amount'       => $data['amount'],
                'type'         => 'credit',
                'status'       => 'success',
                'provider'     => 'shanono',
                'provider_ref' => $data['reference'],
                'meta'         => $data,
            ]);
        });

        return response()->json(['status' => 'ok']);
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
                $payout->update([
                    'status' => PayoutStatusEnums::FAILED,
                ]);

                $wallet = Wallet::where('user_id', $payout->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    $wallet->increment('available_balance', $payout->amount);
                    $wallet->increment('total_balance', $payout->amount);


                    WalletTransaction::create([
                        'wallet_id'    => $wallet->id,
                        'user_id'      => $payout->user_id,
                        'reference'    => Str::uuid(),
                        'amount'       => $payout->amount,
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

        return response()->json(['status' => 'ok']);
    }

    protected function verifySignature(Request $request): void
    {
        $signature = $request->header('X-Signature');
        $payload   = $request->getContent();

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

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature');
        }
    }
}
