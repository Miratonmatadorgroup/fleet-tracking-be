<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\WalletTransactionStatusEnums;

class ShanonoBillsPaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-Signature');
        $payload   = $request->getContent();

        $expected = hash_hmac(
            'sha256',
            $payload,
            config('services.shanono_bank.webhook_secret')
        );

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->input('data');

        DB::transaction(function () use ($data) {

            $txn = WalletTransaction::where('reference', $data['reference'])
                ->where('status', WalletTransactionStatusEnums::PENDING)
                ->lockForUpdate()
                ->first();

            if (! $txn) {
                return;
            }

            $wallet = $txn->wallet;

            $status = strtolower($data['status'] ?? '');

            if (in_array($status, ['success', 'successful'])) {

                // Debit user NOW
                $wallet->decrement('available_balance', $txn->amount);

                $txn->update([
                    'status' => WalletTransactionStatusEnums::SUCCESS,
                ]);
            } elseif ($status === 'failed') {

                $txn->update([
                    'status' => WalletTransactionStatusEnums::FAILED,
                ]);
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
