<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SecurityService;
use Illuminate\Support\Facades\Log;

class ApiRateLimit
{
    protected $limiter;
    protected $securityService;

    public function __construct(RateLimiter $limiter, SecurityService $securityService)
    {
        $this->limiter = $limiter;
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        // Scan for security threats
        $threats = $this->securityService->scanForThreats($request);
        if (!empty($threats)) {
            $criticalThreats = array_filter($threats, fn($threat) => $threat['severity'] === 'critical');
            if (!empty($criticalThreats)) {
                Log::error('Critical security threat detected, blocking request', [
                    'ip' => $request->ip(),
                    'endpoint' => $request->path(),
                    'threats' => $criticalThreats
                ]);
                
                return response()->json([
                    'error' => 'Request blocked for security reasons'
                ], 403);
            }
        }

        $key = $this->resolveRequestSignature($request);

        // Apply enhanced rate limiting with security service
        if (!$this->securityService->applyRateLimit($request, $key, (int)$maxAttempts, (int)$decayMinutes)) {
            throw $this->buildException($request, $key, (int)$maxAttempts, $this->limiter->availableIn($key));
        }

        $response = $next($request);

        return $this->addHeaders(
            $response,
            (int)$maxAttempts,
            $this->calculateRemainingAttempts($key, (int)$maxAttempts)
        );
    }

    /**
     * Resolve the request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return sha1('api_rate_limit:' . $user->id);
        }

        return sha1('api_rate_limit:' . $request->ip());
    }

    /**
     * Create a 'too many attempts' exception.
     */
    protected function buildException(Request $request, string $key, int $maxAttempts, int $retryAfter): ThrottleRequestsException
    {
        $exception = new ThrottleRequestsException('Too Many Attempts.');
        $exception->headers = $this->getHeaders($maxAttempts, $this->calculateRemainingAttempts($key, $maxAttempts), $retryAfter);

        return $exception;
    }

    /**
     * Add the limit header information to the given response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): Response
    {
        $response->headers->add(
            $this->getHeaders($maxAttempts, $remainingAttempts, $retryAfter)
        );

        return $response;
    }

    /**
     * Get the limit headers information.
     */
    protected function getHeaders(int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): array
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if (!is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = now()->addSeconds($retryAfter)->timestamp;
        }

        return $headers;
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }
}