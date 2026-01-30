<?php

namespace App\Http\Controllers\Api;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class ExternalAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        try {
        Log::info('Requesting token from: ' . route('passport.token'));

            $response = Http::asForm()->post('http://localhost:8000/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'scope' => $request->scope ?? '',
            ]);

            if ($response->successful()) {
                return successResponse('Token issued successfully', $response->json());
            }

            Log::error('Token request failed', [
                'body' => $response->body(),
                'status' => $response->status(),
            ]);

            return failureResponse(
                $response->json()['message'] ?? 'Unable to generate token',
                $response->status(),
                'token_error'
            );
        } catch (Throwable $th) {
            return failureResponse(
                'Something went wrong while generating the token',
                500,
                'server_error',
                $th
            );
        }
    }
}
