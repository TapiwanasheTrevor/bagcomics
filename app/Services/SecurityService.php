<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityService
{
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOGIN_LOCKOUT_MINUTES = 15;
    const API_RATE_LIMIT_PER_MINUTE = 60;
    const UPLOAD_RATE_LIMIT_PER_HOUR = 20;

    /**
     * Validate and sanitize file uploads
     */
    public function validateFileUpload(Request $request, string $fieldName, array $allowedTypes = ['pdf'], int $maxSizeMB = 50): array
    {
        $file = $request->file($fieldName);
        $errors = [];

        if (!$file) {
            return ['valid' => false, 'errors' => ['No file uploaded']];
        }

        // Check file size
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;
        if ($file->getSize() > $maxSizeBytes) {
            $errors[] = "File size exceeds {$maxSizeMB}MB limit";
        }

        // Check file type
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = "File type '{$extension}' not allowed. Allowed types: " . implode(', ', $allowedTypes);
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $allowedMimeTypes = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif']
        ];

        $validMimeTypes = [];
        foreach ($allowedTypes as $type) {
            if (isset($allowedMimeTypes[$type])) {
                $validMimeTypes = array_merge($validMimeTypes, $allowedMimeTypes[$type]);
            }
        }

        if (!in_array($mimeType, $validMimeTypes)) {
            $errors[] = "Invalid MIME type '{$mimeType}'";
        }

        // Check file content (basic security scan)
        if ($this->containsSuspiciousContent($file)) {
            $errors[] = "File contains potentially malicious content";
            Log::warning('Suspicious file upload detected', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $mimeType,
                'user_ip' => request()->ip()
            ]);
        }

        // Sanitize filename
        $sanitizedName = $this->sanitizeFilename($file->getClientOriginalName());

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_name' => $sanitizedName,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $mimeType
        ];
    }

    /**
     * Apply rate limiting to API endpoints
     */
    public function applyRateLimit(Request $request, string $key = null, int $maxAttempts = null, int $decayMinutes = 1): bool
    {
        $key = $key ?: $this->generateRateLimitKey($request);
        $maxAttempts = $maxAttempts ?: self::API_RATE_LIMIT_PER_MINUTE;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path()
            ]);
            return false;
        }

        RateLimiter::hit($key, $decayMinutes * 60);
        return true;
    }

    /**
     * Apply login attempt rate limiting
     */
    public function checkLoginAttempts(Request $request): array
    {
        $key = $this->getLoginRateLimitKey($request);
        
        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            
            Log::warning('Login rate limit exceeded', [
                'ip' => $request->ip(),
                'email' => $request->input('email'),
                'seconds_remaining' => $seconds
            ]);
            
            return [
                'allowed' => false,
                'seconds_remaining' => $seconds,
                'message' => "Too many login attempts. Try again in " . ceil($seconds / 60) . " minutes."
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Record failed login attempt
     */
    public function recordFailedLogin(Request $request): void
    {
        $key = $this->getLoginRateLimitKey($request);
        RateLimiter::hit($key, self::LOGIN_LOCKOUT_MINUTES * 60);
        
        Log::warning('Failed login attempt', [
            'ip' => $request->ip(),
            'email' => $request->input('email'),
            'user_agent' => $request->userAgent(),
            'attempts' => RateLimiter::attempts($key)
        ]);
    }

    /**
     * Clear login attempts after successful login
     */
    public function clearLoginAttempts(Request $request): void
    {
        $key = $this->getLoginRateLimitKey($request);
        RateLimiter::clear($key);
    }

    /**
     * Validate and sanitize user input
     */
    public function validateInput(array $data, array $rules, array $messages = []): array
    {
        // Add security-focused validation rules
        $securityRules = [];
        
        foreach ($rules as $field => $rule) {
            // Add XSS protection for text fields
            if (Str::contains($rule, 'string')) {
                $securityRules[$field] = $rule . '|no_script_tags';
            } else {
                $securityRules[$field] = $rule;
            }
        }

        // Custom validator for script tags
        Validator::extend('no_script_tags', function ($attribute, $value, $parameters, $validator) {
            return !preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', $value);
        });

        Validator::replacer('no_script_tags', function ($message, $attribute, $rule, $parameters) {
            return 'The ' . $attribute . ' field contains prohibited content.';
        });

        $validator = Validator::make($data, $securityRules, $messages);

        if ($validator->fails()) {
            Log::warning('Input validation failed', [
                'errors' => $validator->errors()->toArray(),
                'data_keys' => array_keys($data),
                'ip' => request()->ip()
            ]);
        }

        return [
            'valid' => !$validator->fails(),
            'errors' => $validator->errors()->toArray(),
            'sanitized_data' => $this->sanitizeInputData($data)
        ];
    }

    /**
     * Check for SQL injection patterns
     */
    public function detectSqlInjection(string $input): bool
    {
        $sqlPatterns = [
            '/(\s|^)(union|select|insert|update|delete|drop|create|alter|exec|execute)\s/i',
            '/(\s|^)(or|and)\s+[\w\'"]+\s*=\s*[\w\'"]+/i',
            '/(\s|^)(or|and)\s+\d+\s*=\s*\d+/i',
            '/[\'";]\s*(or|and|union|select)/i',
            '/(^|\s)(--|\/\*)/i'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                Log::warning('Potential SQL injection detected', [
                    'input' => substr($input, 0, 100),
                    'pattern' => $pattern,
                    'ip' => request()->ip()
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize filename for safe storage
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^\w\-_\.]/', '_', $filename);
        
        // Prevent double extensions
        $filename = preg_replace('/\.+/', '.', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 95 - strlen($extension)) . '.' . $extension;
        }
        
        return $filename;
    }

    /**
     * Generate CSRF token for forms
     */
    public function generateCsrfToken(): string
    {
        return csrf_token();
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken(Request $request): bool
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        return hash_equals(session()->token(), $token);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        Log::channel('security')->warning($event, array_merge($context, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id()
        ]));
    }

    /**
     * Check if request contains suspicious patterns
     */
    public function scanForThreats(Request $request): array
    {
        $threats = [];

        // Check for XSS attempts
        $allInput = $request->all();
        foreach ($allInput as $key => $value) {
            if (is_string($value) && $this->containsXss($value)) {
                $threats[] = [
                    'type' => 'XSS',
                    'field' => $key,
                    'severity' => 'high'
                ];
            }
        }

        // Check for SQL injection
        foreach ($allInput as $key => $value) {
            if (is_string($value) && $this->detectSqlInjection($value)) {
                $threats[] = [
                    'type' => 'SQL_INJECTION',
                    'field' => $key,
                    'severity' => 'critical'
                ];
            }
        }

        // Check for path traversal
        foreach ($allInput as $key => $value) {
            if (is_string($value) && $this->containsPathTraversal($value)) {
                $threats[] = [
                    'type' => 'PATH_TRAVERSAL',
                    'field' => $key,
                    'severity' => 'high'
                ];
            }
        }

        if (!empty($threats)) {
            $this->logSecurityEvent('Security threats detected', [
                'threats' => $threats,
                'endpoint' => $request->path(),
                'method' => $request->method()
            ]);
        }

        return $threats;
    }

    /**
     * Generate rate limit key for request
     */
    private function generateRateLimitKey(Request $request): string
    {
        $user = $request->user();
        $identifier = $user ? $user->id : $request->ip();
        return 'api_rate_limit:' . $identifier . ':' . $request->path();
    }

    /**
     * Generate login rate limit key
     */
    private function getLoginRateLimitKey(Request $request): string
    {
        return 'login_attempts:' . $request->ip() . ':' . strtolower($request->input('email', ''));
    }

    /**
     * Check if file contains suspicious content
     */
    private function containsSuspiciousContent($file): bool
    {
        // For PDF files, do basic header check
        if ($file->getMimeType() === 'application/pdf') {
            $handle = fopen($file->getRealPath(), 'rb');
            $header = fread($handle, 8);
            fclose($handle);
            
            return !str_starts_with($header, '%PDF-');
        }

        // For other files, check for executable content
        $content = file_get_contents($file->getRealPath(), false, null, 0, 1024);
        $suspiciousPatterns = [
            '/<%[\s\S]*?%>/',  // PHP tags
            '/<script[\s\S]*?<\/script>/i',  // JavaScript
            '/eval\s*\(/',  // eval function
            '/exec\s*\(/',  // exec function
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize input data
     */
    private function sanitizeInputData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $value = strip_tags($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $sanitized[$key] = trim($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInputData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Check for XSS patterns
     */
    private function containsXss(string $input): bool
    {
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:[^\s]*/i',
            '/on\w+\s*=\s*["\'][^"\']*["\']/',
            '/<iframe\b[^>]*>/i',
            '/<object\b[^>]*>/i',
            '/<embed\b[^>]*>/i'
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for path traversal attempts
     */
    private function containsPathTraversal(string $input): bool
    {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e\\\/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get rate limit headers for response
     */
    public function getRateLimitHeaders(string $key, int $maxAttempts): array
    {
        $attempts = RateLimiter::attempts($key);
        $remaining = max(0, $maxAttempts - $attempts);
        $resetTime = RateLimiter::availableIn($key);

        return [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => now()->addSeconds($resetTime)->timestamp
        ];
    }
}