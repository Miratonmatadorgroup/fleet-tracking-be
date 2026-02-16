<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\NinVerification;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProductionAccessRequest;
use App\Enums\NinVerificationStatusEnums;
use App\Services\UserProvisioningManager;


class SmileIdWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        $this->verifySignature($payload);

        // NIN verification callback
        if (($payload['IDType'] ?? null) === 'NIN') {
            $this->handleNinVerification($payload);
        }

        // CAC / Business verification callback
        if (
            ($payload['ResultCode'] ?? null) === '1012' &&
            ($payload['Actions']['Verify_Business'] ?? null) === 'Verified'
        ) {
            $this->handleSuccessfulCAC($payload);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function verifySignature(array $payload): void
    {
        $expected = base64_encode(
            hash_hmac(
                'sha256',
                $payload['timestamp']
                    . config('services.smile_identity.partner_id')
                    . 'sid_response',
                config('services.smile_identity.api_key'),
                true
            )
        );

        if (! hash_equals($expected, $payload['signature'] ?? '')) {
            abort(403, 'Invalid Smile ID signature');
        }
    }

    /**
     * Handle NIN verification callback
     */
    protected function handleNinVerification(array $payload): void
    {
        $jobId = $payload['PartnerParams']['job_id'] ?? null;

        if (! $jobId) {
            return;
        }

        $verification = NinVerification::where('job_id', $jobId)->first();

        if (! $verification) {
            return;
        }

        $status = ($payload['ResultCode'] ?? null) === '1020'
            ? NinVerificationStatusEnums::APPROVED
            : NinVerificationStatusEnums::REJECTED;

        DB::transaction(function () use ($verification, $payload, $status) {
            $verification->update([
                'status' => $status,
                'result' => $payload,
            ]);

            if ($status === NinVerificationStatusEnums::APPROVED) {
                $user = $verification->user;

                $user->update([
                    'nin_verified_at' => now(),
                ]);

                // Optional: auto-complete KYB if CAC already done
                if ($user->kyb_verified) {
                    // trigger KYB completed event
                }
            }
        });
    }

    /**
     * Handle CAC verification callback
     */
    protected function handleSuccessfulCAC(array $payload): void
    {
        $userId = $payload['PartnerParams']['user_id'] ?? null;

        if (! $userId) {
            return;
        }

        $request = ProductionAccessRequest::where('user_id', $userId)
            ->where('app_type', 'external')
            ->where('status', 'pending')
            ->first();

        if (! $request) {
            return;
        }

        DB::transaction(function () use ($request, $payload) {
            $request->update([
                'status' => 'approved',
                'verified_at' => now(),
                'meta' => [
                    'company_information' => $payload['company_information'] ?? [],
                    'directors' => $payload['directors'] ?? [],
                    'beneficial_owners' => $payload['beneficial_owners'] ?? [],
                ],
            ]);

            $user = $request->user;

            $user->update([
                'production_access_approved_at' => now(),
            ]);

            // Provision API keys
            app(UserProvisioningManager::class)->provision($user);
        });
    }
}
