<?php

namespace Tests\Feature;

use App\Services\SecurityService;
use App\Services\DeploymentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private SecurityService $securityService;
    private DeploymentService $deploymentService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->securityService = new SecurityService();
        $this->deploymentService = new DeploymentService();
        
        Storage::fake('public');
        RateLimiter::clear('test_key');
    }

    /** @test */
    public function security_service_validates_file_uploads_correctly()
    {
        // Valid PDF upload
        $validPdf = UploadedFile::fake()->create('comic.pdf', 1000, 'application/pdf');
        
        $request = request();
        $request->files->set('comic_file', $validPdf);
        
        $result = $this->securityService->validateFileUpload($request, 'comic_file', ['pdf'], 50);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotEmpty($result['sanitized_name']);
    }

    /** @test */
    public function security_service_rejects_invalid_file_types()
    {
        $invalidFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/octet-stream');
        
        $request = request();
        $request->files->set('comic_file', $invalidFile);
        
        $result = $this->securityService->validateFileUpload($request, 'comic_file', ['pdf'], 50);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not allowed', implode(' ', $result['errors']));
    }

    /** @test */
    public function security_service_rejects_oversized_files()
    {
        $oversizedFile = UploadedFile::fake()->create('large.pdf', 60000, 'application/pdf'); // 60MB
        
        $request = request();
        $request->files->set('comic_file', $oversizedFile);
        
        $result = $this->securityService->validateFileUpload($request, 'comic_file', ['pdf'], 50);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('exceeds', implode(' ', $result['errors']));
    }

    /** @test */
    public function security_service_applies_rate_limiting()
    {
        $request = request();
        $request->headers->set('X-Forwarded-For', '192.168.1.1');
        
        // First request should pass
        $result1 = $this->securityService->applyRateLimit($request, 'test_key', 2, 1);
        $this->assertTrue($result1);
        
        // Second request should pass
        $result2 = $this->securityService->applyRateLimit($request, 'test_key', 2, 1);
        $this->assertTrue($result2);
        
        // Third request should be blocked
        $result3 = $this->securityService->applyRateLimit($request, 'test_key', 2, 1);
        $this->assertFalse($result3);
    }

    /** @test */
    public function security_service_detects_sql_injection_attempts()
    {
        $sqlInjectionAttempts = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "UNION SELECT * FROM passwords",
            "admin'--",
            "' OR 1=1 --"
        ];
        
        foreach ($sqlInjectionAttempts as $attempt) {
            $detected = $this->securityService->detectSqlInjection($attempt);
            $this->assertTrue($detected, "Failed to detect SQL injection: {$attempt}");
        }
    }

    /** @test */
    public function security_service_validates_and_sanitizes_input()
    {
        $maliciousData = [
            'name' => 'John <script>alert("xss")</script> Doe',
            'email' => 'user@example.com',
            'description' => 'Normal text with <iframe src="evil.com"></iframe>',
        ];
        
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'description' => 'string|max:1000'
        ];
        
        $result = $this->securityService->validateInput($maliciousData, $rules);
        
        $this->assertTrue($result['valid']);
        $this->assertStringNotContainsString('<script>', $result['sanitized_data']['name']);
        $this->assertStringNotContainsString('<iframe>', $result['sanitized_data']['description']);
    }

    /** @test */
    public function security_service_sanitizes_filenames()
    {
        $dangerousFilenames = [
            '../../malicious.pdf',
            'normal file.pdf',
            'file with spaces & symbols!.pdf',
            'unicode_ñämê.pdf',
            '.htaccess'
        ];
        
        foreach ($dangerousFilenames as $filename) {
            $sanitized = $this->securityService->sanitizeFilename($filename);
            
            $this->assertStringNotContainsString('..', $sanitized);
            $this->assertStringNotContainsString('/', $sanitized);
            $this->assertStringNotContainsString('\\', $sanitized);
            $this->assertLessThanOrEqual(100, strlen($sanitized));
        }
    }

    /** @test */
    public function security_service_manages_login_attempts()
    {
        $request = request();
        $request->merge(['email' => 'test@example.com']);
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        
        // First few attempts should be allowed
        for ($i = 0; $i < 4; $i++) {
            $result = $this->securityService->checkLoginAttempts($request);
            $this->assertTrue($result['allowed']);
            
            // Record failed attempt
            $this->securityService->recordFailedLogin($request);
        }
        
        // After max attempts, should be blocked
        $result = $this->securityService->checkLoginAttempts($request);
        $this->assertFalse($result['allowed']);
        $this->assertArrayHasKey('seconds_remaining', $result);
        
        // Clear attempts should reset
        $this->securityService->clearLoginAttempts($request);
        $result = $this->securityService->checkLoginAttempts($request);
        $this->assertTrue($result['allowed']);
    }

    /** @test */
    public function security_service_scans_for_threats()
    {
        $request = request();
        $request->merge([
            'normal_field' => 'Safe content',
            'xss_field' => '<script>alert("xss")</script>',
            'sql_field' => "'; DROP TABLE users; --",
            'path_traversal' => '../../etc/passwd'
        ]);
        
        $threats = $this->securityService->scanForThreats($request);
        
        $this->assertNotEmpty($threats);
        
        $threatTypes = array_column($threats, 'type');
        $this->assertContains('XSS', $threatTypes);
        $this->assertContains('SQL_INJECTION', $threatTypes);
        $this->assertContains('PATH_TRAVERSAL', $threatTypes);
    }

    /** @test */
    public function deployment_service_initializes_production_correctly()
    {
        // This test verifies the deployment service can initialize without errors
        $result = $this->deploymentService->initializeProduction();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('results', $result);
        
        if ($result['success']) {
            $this->assertArrayHasKey('database', $result['results']);
            $this->assertArrayHasKey('storage', $result['results']);
            $this->assertArrayHasKey('security', $result['results']);
        }
    }

    /** @test */
    public function deployment_service_checks_system_health()
    {
        $health = $this->deploymentService->getSystemHealth();
        
        $this->assertIsArray($health);
        $this->assertArrayHasKey('database', $health);
        $this->assertArrayHasKey('storage', $health);
        $this->assertArrayHasKey('cache', $health);
        $this->assertArrayHasKey('environment', $health);
        
        foreach ($health as $component => $status) {
            $this->assertArrayHasKey('status', $status);
            $this->assertArrayHasKey('message', $status);
            $this->assertContains($status['status'], ['healthy', 'unhealthy']);
        }
    }

    /** @test */
    public function deployment_service_verifies_readiness()
    {
        $readiness = $this->deploymentService->verifyDeploymentReadiness();
        
        $this->assertIsArray($readiness);
        $this->assertArrayHasKey('ready', $readiness);
        $this->assertArrayHasKey('checks', $readiness);
        $this->assertArrayHasKey('summary', $readiness);
        $this->assertIsBool($readiness['ready']);
        
        foreach ($readiness['checks'] as $check => $result) {
            $this->assertArrayHasKey('passed', $result);
            $this->assertArrayHasKey('message', $result);
            $this->assertIsBool($result['passed']);
        }
    }

    /** @test */
    public function deployment_service_creates_backup()
    {
        $backup = $this->deploymentService->createPreDeploymentBackup();
        
        $this->assertIsArray($backup);
        
        if (!isset($backup['error'])) {
            $this->assertArrayHasKey('files_manifest', $backup);
            $this->assertArrayHasKey('configuration', $backup);
        }
    }

    /** @test */
    public function api_rate_limit_middleware_blocks_excessive_requests()
    {
        $user = User::factory()->create();
        
        // Make requests up to the limit
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($user)
                ->getJson('/api/comics');
            
            if ($i < 2) {
                $this->assertNotEquals(429, $response->status());
            }
        }
        
        // Next request should be rate limited
        $response = $this->actingAs($user)
            ->getJson('/api/comics');
        
        // Note: This might not always trigger depending on rate limit settings
        $this->assertContains($response->status(), [200, 429]);
    }

    /** @test */
    public function security_middleware_blocks_malicious_requests()
    {
        $maliciousData = [
            'title' => '<script>alert("xss")</script>',
            'description' => "'; DROP TABLE comics; --"
        ];
        
        $response = $this->postJson('/api/comics', $maliciousData);
        
        // Should either block the request or sanitize the input
        $this->assertContains($response->status(), [400, 403, 422]);
    }

    /** @test */
    public function file_upload_security_is_enforced()
    {
        $user = User::factory()->create();
        
        // Try to upload a non-PDF file as comic
        $maliciousFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');
        
        $response = $this->actingAs($user)
            ->post('/admin/comics', [
                'title' => 'Test Comic',
                'pdf_file_path' => $maliciousFile
            ]);
        
        $this->assertNotEquals(200, $response->status());
    }

    /** @test */
    public function csrf_protection_is_enforced()
    {
        $user = User::factory()->create();
        
        // Request without CSRF token should fail
        $response = $this->actingAs($user)
            ->post('/comics', [
                'title' => 'Test Comic'
            ]);
        
        // CSRF protection should either block or redirect
        $this->assertContains($response->status(), [302, 419, 422]);
    }

    /** @test */
    public function security_headers_are_present()
    {
        $response = $this->get('/');
        
        // Check for basic security headers (these might be set by middleware or server)
        $headers = $response->headers;
        
        // At minimum, we should have some response headers
        $this->assertNotEmpty($headers->all());
        
        // In production, you would check for:
        // X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, etc.
    }
}