<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            Log::warning('Admin access denied - not authenticated', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Log the attempt
        Log::info('Admin access attempt', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_admin' => $user->is_admin ?? false,
            'ip' => $request->ip(),
        ]);

        // Check if user has admin access
        $hasAdminAccess = $this->checkAdminAccess($user);
        
        if (!$hasAdminAccess) {
            Log::warning('Admin access denied - insufficient privileges', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);
            
            abort(403, 'Admin access required');
        }

        Log::info('Admin access granted', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $next($request);
    }

    private function checkAdminAccess($user): bool
    {
        // Check is_admin field
        if (isset($user->is_admin) && $user->is_admin) {
            return true;
        }

        // Check ADMIN_EMAILS environment variable
        $adminEmails = array_map('trim', explode(',', env('ADMIN_EMAILS', '')));
        $adminEmails = array_filter($adminEmails); // Remove empty values

        if (!empty($adminEmails) && in_array($user->email, $adminEmails)) {
            return true;
        }

        return false;
    }
}