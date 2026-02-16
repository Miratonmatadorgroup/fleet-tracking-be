<?php

namespace App\Http\Controllers\Api;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\WebhookSecret;
use App\Http\Controllers\Controller;

class WebhookSecretController extends Controller
{
    public function generate(Request $request)
    {
        try {
            $validated = $request->validate([
                'environment' => 'required|in:staging,production',
            ]);

            // Check if secret already exists
            $existing = WebhookSecret::where('service', 'shanono')
                ->where('environment', $validated['environment'])
                ->first();

            if ($existing) {
                return successResponse(
                    'Webhook secret already exists',
                    [
                        'service'     => 'shanono',
                        'environment' => $existing->environment,
                        'secret'      => $existing->secret,
                    ]
                );
            }

            // Generate secure webhook secret
            $secret = sprintf(
                'shanono_whsec_%s_%s_%s',
                $validated['environment'],
                Str::uuid(),
                Str::random(32)
            );

            $record = WebhookSecret::create([
                'service'     => 'shanono',
                'environment' => $validated['environment'],
                'secret'      => $secret,
            ]);

            return successResponse(
                'Webhook secret created successfully',
                [
                    'service'     => $record->service,
                    'environment' => $record->environment,
                    'secret'      => $record->secret,
                ]
            );
        } catch (Throwable $th) {
            return failureResponse(
                'Unable to generate webhook secret',
                500,
                'WEBHOOK_SECRET_ERROR',
                $th
            );
        }
    }
}
