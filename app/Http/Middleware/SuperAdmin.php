<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class SuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user(); 

        if (!$user || !$user->hasRole('superAdmin')) {
            return response()->json(['message' => 'Unauthorized. Only Super Admins can access this route.'], 403);
        }

        return $next($request);
    }
}
