<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalAuth
{
    /**
     * Handle an incoming request.
     * 
     * This middleware attempts to authenticate the user but doesn't fail if authentication is not possible.
     * It allows both authenticated and unauthenticated users to proceed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Attempt to authenticate using Sanctum if token is provided
        if ($request->bearerToken() || $request->hasSession()) {
            try {
                Auth::guard('sanctum')->check();
            } catch (\Exception $e) {
                // Silently fail - allow request to proceed as guest
            }
        }

        return $next($request);
    }
}