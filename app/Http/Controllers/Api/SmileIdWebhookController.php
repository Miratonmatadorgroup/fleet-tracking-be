<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProductionAccessRequest;
use App\Services\UserProvisioningManager;

class SmileIdWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        // Verify signature
        $expected = base64_encode(
            hash_hmac(
                'sha256',
                $payload['timestamp'] . config('services.smile_identity.partner_id') . 'sid_response',
                config('services.smile_identity.api_key'),
                true
            )
        );

        if ($expected !== $payload['signature']) {
            abort(403, 'Invalid Smile ID signature');
        }

        if (
            ($payload['ResultCode'] ?? null) === '1012' &&
            ($payload['Actions']['Verify_Business'] ?? null) === 'Verified'
        ) {
            $this->handleSuccessfulCAC($payload);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleSuccessfulCAC(array $payload): void
    {
        $userId = $payload['PartnerParams']['user_id'];

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

            //This triggers API key generation
            app(UserProvisioningManager::class)->provision($user);
        });
    }
}
