<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class UpdateUserActivity
{
    public function handle($request, Closure $next)
    {
        /** @var User|null $user */
        $user = $request->user();

        Log::info('Middleware user check', [
            'auth_user' => $request->user(),
            'header_authorization' => $request->header('Authorization'),
        ]);

        if (!$user) {
            return $next($request);
        }

        $passportToken = method_exists($user, 'token') ? $user->token() : null;
        $tokenId = $passportToken?->id;

        $deviceToken = UserToken::where('user_id', $user->id)
            ->where('id', $tokenId)
            ->first();

        // Per-role timeout
        $role = $user->getRoleNames()->first() ?? 'default';
        $timeout = config("activity.timeouts.$role")
            ?? config("activity.timeouts.default");

        if ($deviceToken) {

            $inactive = now()->diffInMinutes($deviceToken->last_activity);

            if ($inactive >= $timeout) {

                if ($passportToken && method_exists($passportToken, 'revoke')) {
                    $passportToken->revoke();
                }

                $deviceToken->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Session expired due to inactivity.',
                    'type'    => 'session_expired',
                ], 401);
            }

            $deviceToken->update([
                'last_activity' => now(),
                'ip_address'    => $request->ip(),
            ]);
        }

        User::where('id', $user->id)->update([
            'last_activity' => now(),
        ]);

        return $next($request);
    }
}
