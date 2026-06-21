<?php

namespace App\Http\Controllers\Api;

use App\Actions\Authentication\GoogleAuthAction;
use App\Actions\Authentication\GoogleTokenAuthAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\SocialiteManager;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
        $provider = app(SocialiteManager::class)->driver('google');

        return $provider->stateless()->redirect();
    }

    public function callback(GoogleAuthAction $action)
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
        $provider = app(\Laravel\Socialite\SocialiteManager::class)->driver('google');

        $googleUser = $provider->stateless()->user();

        if (!$googleUser->getEmail()) {
            return failureResponse(
                'Google account does not have an email address',
                422,
                'google_email_missing'
            );
        }
        $normalizedUser = (object) [
            'id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName(),
            // 'email_verified' => $googleUser->user['email_verified'] ?? true,
            'email_verified' => $googleUser->user['email_verified'] ?? false,
        ];

        $raw = $googleUser->user ?? [];

        if (empty($raw['email_verified'])) {
            return failureResponse(
                'Google email not verified',
                403,
                'google_email_not_verified'
            );
        }

        session(['registration_type' => 'developer']);
        $isDev = session('registration_type') === 'developer';

        $data = $action->execute($normalizedUser, $isDev);

        $user = $data['user'];
        $wallet = $data['wallet'];
        $isNewUser = $data['is_new_user'];

        $token = $user->createToken('authToken')->accessToken;
        $user->roles = $user->getRoleNames()->values();


        return successResponse("Login successful", [
            'user' => $user,
            'roles' => $user->getRoleNames()->values(),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'is_new_user' => $isNewUser
        ]);
    }


    public function login(Request $request, GoogleTokenAuthAction $action)
    {
        $request->validate([
            'access_token' => 'required|string',
            'registration_type' => 'nullable|in:user,developer',
        ]);

        $googleResponse = Http::get(
            'https://www.googleapis.com/oauth2/v3/userinfo',
            [
                'access_token' => $request->access_token
            ]
        );

        if (!$googleResponse->ok()) {
            return failureResponse('Invalid Google token', 401, 'invalid_google_token');
        }

        $googleUser = (object) $googleResponse->json();

        if (!$googleUser->email_verified) {
            return failureResponse(
                'Google email not verified',
                403,
                'google_email_not_verified'
            );
        }

        $normalizedUser = (object) [
            'id' => $googleUser->sub,
            'email' => $googleUser->email,
            'name' => $googleUser->name ?? 'Unknown',
            'email_verified' => $googleUser->email_verified,
        ];

        $isDev = ($request->registration_type ?? 'user') === 'developer';

        // $data = $action->execute($googleUser, $isDev);
        $data = $action->execute($normalizedUser, $isDev);

        return $this->loginResponse($data);
    }


    protected function loginResponse(array $data)
    {
        $user = $data['user'];
        $wallet = $data['wallet'];
        $isNewUser = $data['is_new_user'];

        $token = $user->createToken('authToken')->accessToken;

        $user->roles = $user->getRoleNames()->values();

        // Get first role
        $role = $user->getRoleNames()->first();

        // Get active subscription
        $subscription = $user->activeSubscription()
            ->with('plan')
            ->first();

        $subscriptionData = null;

        if ($subscription) {
            $subscriptionData = [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'is_active' => $subscription->isActive(),
                'days_until_expiry' => $subscription->daysUntilExpiry(),
                'auto_renew' => $subscription->auto_renew,
                'is_trial' => $subscription->is_trial,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                ] : null,
            ];
        }

        return successResponse('Login successful', [
            'user' => $user,
            'wallet' => $wallet,
            'role' => $role,
            'subscription' => $subscriptionData,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'is_new_user' => $isNewUser,
        ]);
    }
}
