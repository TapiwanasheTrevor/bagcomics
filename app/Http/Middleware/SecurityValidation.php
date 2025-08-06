<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SecurityService;

class SecurityValidation
{
    protected SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip security validation for safe methods and specific routes
        if ($this->shouldSkipValidation($request)) {
            return $next($request);
        }

        // Verify CSRF token for non-API routes
        if (!$request->is('api/*') && !$this->securityService->verifyCsrfToken($request)) {
            $this->securityService->logSecurityEvent('CSRF token validation failed', [
                'endpoint' => $request->path(),
                'method' => $request->method()
            ]);

            return response()->json([
                'error' => 'CSRF token mismatch'
            ], 419);
        }

        // Validate file uploads
        $this->validateFileUploads($request);

        // Scan request for threats
        $threats = $this->securityService->scanForThreats($request);
        if (!empty($threats)) {
            $highSeverityThreats = array_filter($threats, function ($threat) {
                return in_array($threat['severity'], ['high', 'critical']);
            });

            if (!empty($highSeverityThreats)) {
                return response()->json([
                    'error' => 'Request contains security violations'
                ], 400);
            }
        }

        return $next($request);
    }

    /**
     * Determine if security validation should be skipped
     */
    private function shouldSkipValidation(Request $request): bool
    {
        // Skip for safe HTTP methods
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        // Skip for webhook endpoints
        if ($request->is('webhooks/*')) {
            return true;
        }

        // Skip for health check endpoints
        if ($request->is('health') || $request->is('status')) {
            return true;
        }

        return false;
    }

    /**
     * Validate file uploads in request
     */
    private function validateFileUploads(Request $request): void
    {
        $files = $request->allFiles();
        
        foreach ($files as $fieldName => $file) {
            if (is_array($file)) {
                // Handle multiple file uploads
                foreach ($file as $singleFile) {
                    $this->validateSingleFile($fieldName, $singleFile);
                }
            } else {
                $this->validateSingleFile($fieldName, $file);
            }
        }
    }

    /**
     * Validate a single uploaded file
     */
    private function validateSingleFile(string $fieldName, $file): void
    {
        $allowedTypes = $this->getAllowedFileTypes($fieldName);
        $maxSizeMB = $this->getMaxFileSize($fieldName);

        $validation = $this->securityService->validateFileUpload(
            request(),
            $fieldName,
            $allowedTypes,
            $maxSizeMB
        );

        if (!$validation['valid']) {
            $this->securityService->logSecurityEvent('File upload validation failed', [
                'field' => $fieldName,
                'errors' => $validation['errors'],
                'filename' => $validation['original_name'] ?? 'unknown'
            ]);

            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                response()->json([
                    'error' => 'File upload validation failed',
                    'details' => $validation['errors']
                ], 422)
            );
        }
    }

    /**
     * Get allowed file types for a field
     */
    private function getAllowedFileTypes(string $fieldName): array
    {
        $typeMap = [
            'comic_files' => ['pdf'],
            'pdf_file_path' => ['pdf'],
            'cover_image_path' => ['jpg', 'jpeg', 'png'],
            'cover_images' => ['jpg', 'jpeg', 'png'],
            'avatar' => ['jpg', 'jpeg', 'png'],
            'profile_image' => ['jpg', 'jpeg', 'png']
        ];

        return $typeMap[$fieldName] ?? ['pdf', 'jpg', 'jpeg', 'png'];
    }

    /**
     * Get maximum file size for a field
     */
    private function getMaxFileSize(string $fieldName): int
    {
        $sizeMap = [
            'comic_files' => 100, // 100MB for comics
            'pdf_file_path' => 100,
            'cover_image_path' => 10, // 10MB for images
            'cover_images' => 10,
            'avatar' => 5, // 5MB for avatars
            'profile_image' => 5
        ];

        return $sizeMap[$fieldName] ?? 50; // Default 50MB
    }
}