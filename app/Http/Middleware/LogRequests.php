<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if (str_contains($request->getRequestUri(), 'livewire')) {
                Log::info('Livewire request received:', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'session_id' => session()->getId(),
                    'csrf_token_session' => session()->token(),
                    'csrf_token_header' => $request->header('x-csrf-token'),
                    'headers' => $request->headers->all(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LogRequests middleware error: ' . $e->getMessage());
        }

        return $next($request);
    }
}