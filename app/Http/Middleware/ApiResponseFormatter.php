<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApiResponseFormatter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        // Only format JSON responses for API routes
        if (!$request->is('api/*')) {
            return $response;
        }

        // Handle non-JSON responses by converting them to JSON for API routes
        if (!$response instanceof JsonResponse) {
            // Convert authentication errors to JSON
            if ($response->getStatusCode() === 401) {
                $response = response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            } elseif ($response->getStatusCode() === 403) {
                $response = response()->json([
                    'message' => 'Forbidden.'
                ], 403);
            } else {
                return $response;
            }
        }

        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();

        // Format successful responses
        if ($statusCode >= 200 && $statusCode < 300) {
            $formattedData = [
                'success' => true,
                'timestamp' => now()->toISOString(),
            ];

            // Add pagination info if present
            if (isset($data['pagination'])) {
                $formattedData['data'] = $data['data'] ?? [];
                $formattedData['pagination'] = $data['pagination'];
            } else {
                $formattedData['data'] = $data;
            }

            // Add meta information if present
            if (isset($data['meta'])) {
                $formattedData['meta'] = $data['meta'];
            }

            return response()->json($formattedData, $statusCode, $response->headers->all());
        }

        // Format error responses
        $errorData = [
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode($statusCode, $data),
                'message' => $this->getErrorMessage($statusCode, $data),
                'timestamp' => now()->toISOString(),
            ]
        ];

        // Add validation errors if present
        if (isset($data['errors'])) {
            $errorData['error']['validation_errors'] = $data['errors'];
        }

        // Add additional error details if present
        if (isset($data['details'])) {
            $errorData['error']['details'] = $data['details'];
        }

        return response()->json($errorData, $statusCode, $response->headers->all());
    }

    /**
     * Get error code based on status code and data.
     */
    protected function getErrorCode(int $statusCode, array $data): string
    {
        if (isset($data['code'])) {
            return $data['code'];
        }

        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            default => 'UNKNOWN_ERROR',
        };
    }

    /**
     * Get error message based on status code and data.
     */
    protected function getErrorMessage(int $statusCode, array $data): string
    {
        if (isset($data['message'])) {
            return $data['message'];
        }

        return match ($statusCode) {
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            default => 'An error occurred',
        };
    }
}