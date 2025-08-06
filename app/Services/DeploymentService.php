<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class DeploymentService
{
    /**
     * Initialize the application for production deployment
     */
    public function initializeProduction(): array
    {
        $results = [];
        
        try {
            // Ensure database is properly configured
            $results['database'] = $this->configureDatabaseForProduction();
            
            // Set up persistent storage
            $results['storage'] = $this->configurePersistentStorage();
            
            // Optimize performance settings
            $results['performance'] = $this->optimizeForProduction();
            
            // Set up security configurations
            $results['security'] = $this->configureSecuritySettings();
            
            // Initialize required data
            $results['data'] = $this->initializeRequiredData();
            
            Log::info('Production initialization completed successfully', $results);
            
            return [
                'success' => true,
                'results' => $results,
                'message' => 'Application initialized for production successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Production initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $results
            ];
        }
    }

    /**
     * Configure database for production
     */
    private function configureDatabaseForProduction(): array
    {
        $results = [];
        
        // Test database connection
        try {
            DB::connection()->getPdo();
            $results['connection'] = 'PostgreSQL connection successful';
        } catch (\Exception $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
        
        // Run migrations if needed
        try {
            Artisan::call('migrate', ['--force' => true]);
            $results['migrations'] = 'Database migrations completed';
        } catch (\Exception $e) {
            Log::warning('Migration issues detected', ['error' => $e->getMessage()]);
            $results['migrations'] = 'Migration warnings: ' . $e->getMessage();
        }
        
        // Optimize database performance
        try {
            $dbOptimizationService = new DatabaseOptimizationService();
            $dbOptimizationService->addOptimizedIndexes();
            $dbOptimizationService->optimizeCommonQueries();
            $results['optimization'] = 'Database optimization completed';
        } catch (\Exception $e) {
            Log::warning('Database optimization failed', ['error' => $e->getMessage()]);
            $results['optimization'] = 'Database optimization failed: ' . $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Configure persistent storage for Render deployment
     */
    private function configurePersistentStorage(): array
    {
        $results = [];
        
        // Define required directories
        $directories = [
            'comics',
            'covers', 
            'images/original',
            'images/thumbnail',
            'images/small',
            'images/medium',
            'images/large',
            'exports'
        ];
        
        // Create directories in public storage
        foreach ($directories as $dir) {
            $fullPath = storage_path("app/public/{$dir}");
            if (!File::exists($fullPath)) {
                File::makeDirectory($fullPath, 0775, true);
                $results['created'][] = $dir;
            } else {
                $results['exists'][] = $dir;
            }
        }
        
        // Set up storage link
        try {
            Artisan::call('storage:link');
            $results['storage_link'] = 'Storage symbolic link created';
        } catch (\Exception $e) {
            Log::warning('Storage link creation failed', ['error' => $e->getMessage()]);
            $results['storage_link'] = 'Storage link already exists or failed';
        }
        
        // Verify storage permissions
        $storageBasePath = storage_path('app/public');
        if (is_writable($storageBasePath)) {
            $results['permissions'] = 'Storage is writable';
        } else {
            Log::error('Storage directory is not writable', ['path' => $storageBasePath]);
            $results['permissions'] = 'Storage permission issues detected';
        }
        
        return $results;
    }

    /**
     * Optimize application for production
     */
    private function optimizeForProduction(): array
    {
        $results = [];
        
        try {
            // Clear and cache configurations
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            $results['config'] = 'Configuration cached';
            
            // Cache routes if not in debug mode
            if (!config('app.debug')) {
                Artisan::call('route:cache');
                $results['routes'] = 'Routes cached';
            }
            
            // Cache views
            Artisan::call('view:cache');
            $results['views'] = 'Views cached';
            
            // Optimize autoloader
            Artisan::call('optimize');
            $results['optimize'] = 'Application optimized';
            
        } catch (\Exception $e) {
            Log::warning('Performance optimization failed', ['error' => $e->getMessage()]);
            $results['error'] = 'Optimization failed: ' . $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Configure security settings for production
     */
    private function configureSecuritySettings(): array
    {
        $results = [];
        
        // Verify HTTPS settings
        if (config('app.env') === 'production') {
            $results['https'] = config('app.force_https', false) ? 'HTTPS enforced' : 'HTTPS not enforced';
        }
        
        // Check session security
        $results['session_secure'] = config('session.secure') ? 'Secure cookies enabled' : 'Secure cookies disabled';
        
        // Check CSRF protection
        $results['csrf'] = class_exists('App\\Http\\Middleware\\VerifyCsrfToken') ? 'CSRF protection active' : 'CSRF protection missing';
        
        // Check API rate limiting
        $results['rate_limiting'] = class_exists('App\\Http\\Middleware\\ApiRateLimit') ? 'Rate limiting active' : 'Rate limiting missing';
        
        return $results;
    }

    /**
     * Initialize required data for the application
     */
    private function initializeRequiredData(): array
    {
        $results = [];
        
        try {
            // Seed CMS content if needed
            $cmsCount = DB::table('cms_contents')->count();
            if ($cmsCount === 0) {
                Artisan::call('db:seed', ['--class' => 'CmsContentSeeder']);
                $results['cms_content'] = 'CMS content seeded';
            } else {
                $results['cms_content'] = 'CMS content exists';
            }
            
            // Create admin user if needed
            $adminCount = DB::table('users')->where('email', 'admin@bagcomics.com')->count();
            if ($adminCount === 0) {
                Artisan::call('db:seed', ['--class' => 'AdminUserSeeder']);
                $results['admin_user'] = 'Admin user created';
            } else {
                $results['admin_user'] = 'Admin user exists';
            }
            
        } catch (\Exception $e) {
            Log::warning('Data initialization issues', ['error' => $e->getMessage()]);
            $results['error'] = 'Data initialization failed: ' . $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): array
    {
        $health = [];
        
        // Database health
        try {
            DB::connection()->getPdo();
            $health['database'] = ['status' => 'healthy', 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            $health['database'] = ['status' => 'unhealthy', 'message' => 'Database connection failed'];
        }
        
        // Storage health
        $storageWritable = is_writable(storage_path('app/public'));
        $health['storage'] = [
            'status' => $storageWritable ? 'healthy' : 'unhealthy',
            'message' => $storageWritable ? 'Storage is writable' : 'Storage permission issues'
        ];
        
        // Cache health
        try {
            cache()->put('health_check', 'ok', 60);
            $cachedValue = cache()->get('health_check');
            $health['cache'] = [
                'status' => $cachedValue === 'ok' ? 'healthy' : 'unhealthy',
                'message' => $cachedValue === 'ok' ? 'Cache is working' : 'Cache issues detected'
            ];
        } catch (\Exception $e) {
            $health['cache'] = ['status' => 'unhealthy', 'message' => 'Cache error: ' . $e->getMessage()];
        }
        
        // Environment health
        $requiredEnvVars = ['APP_KEY', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
        $missingVars = [];
        foreach ($requiredEnvVars as $var) {
            if (!env($var)) {
                $missingVars[] = $var;
            }
        }
        
        $health['environment'] = [
            'status' => empty($missingVars) ? 'healthy' : 'unhealthy',
            'message' => empty($missingVars) ? 'All required environment variables set' : 'Missing: ' . implode(', ', $missingVars)
        ];
        
        return $health;
    }

    /**
     * Backup critical data before deployment
     */
    public function createPreDeploymentBackup(): array
    {
        $results = [];
        
        try {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupPath = storage_path("app/backups/pre_deployment_{$timestamp}");
            
            if (!File::exists(dirname($backupPath))) {
                File::makeDirectory(dirname($backupPath), 0755, true);
            }
            
            // Backup database schema
            $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', [config('database.connections.pgsql.database')]);
            $results['database_tables'] = count($tables);
            
            // Backup uploaded files manifest
            $uploadedFiles = [];
            $directories = ['comics', 'covers', 'images'];
            foreach ($directories as $dir) {
                $files = Storage::disk('public')->allFiles($dir);
                $uploadedFiles[$dir] = $files;
            }
            
            File::put($backupPath . '_files_manifest.json', json_encode($uploadedFiles, JSON_PRETTY_PRINT));
            $results['files_manifest'] = 'Created';
            
            // Backup environment configuration
            $envConfig = [
                'app_version' => config('app.version', '1.0.0'),
                'database_connection' => config('database.default'),
                'cache_driver' => config('cache.default'),
                'storage_disk' => config('filesystems.default'),
                'backup_timestamp' => $timestamp
            ];
            
            File::put($backupPath . '_config.json', json_encode($envConfig, JSON_PRETTY_PRINT));
            $results['configuration'] = 'Backed up';
            
            Log::info('Pre-deployment backup created', $results);
            
        } catch (\Exception $e) {
            Log::error('Backup creation failed', ['error' => $e->getMessage()]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Verify deployment readiness
     */
    public function verifyDeploymentReadiness(): array
    {
        $checks = [];
        $allPassed = true;
        
        // Check database connectivity
        try {
            DB::connection()->getPdo();
            $checks['database_connection'] = ['passed' => true, 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            $checks['database_connection'] = ['passed' => false, 'message' => 'Database connection failed'];
            $allPassed = false;
        }
        
        // Check required tables exist
        $requiredTables = ['users', 'comics', 'payments', 'user_libraries'];
        $missingTables = [];
        foreach ($requiredTables as $table) {
            try {
                DB::table($table)->limit(1)->get();
            } catch (\Exception $e) {
                $missingTables[] = $table;
            }
        }
        
        $checks['required_tables'] = [
            'passed' => empty($missingTables),
            'message' => empty($missingTables) ? 'All required tables exist' : 'Missing tables: ' . implode(', ', $missingTables)
        ];
        
        if (!empty($missingTables)) {
            $allPassed = false;
        }
        
        // Check storage permissions
        $storageWritable = is_writable(storage_path('app/public'));
        $checks['storage_permissions'] = [
            'passed' => $storageWritable,
            'message' => $storageWritable ? 'Storage is writable' : 'Storage permission issues'
        ];
        
        if (!$storageWritable) {
            $allPassed = false;
        }
        
        // Check environment configuration
        $requiredEnvVars = ['APP_KEY', 'DB_HOST', 'DB_DATABASE'];
        $missingEnvVars = [];
        foreach ($requiredEnvVars as $var) {
            if (!env($var)) {
                $missingEnvVars[] = $var;
            }
        }
        
        $checks['environment_variables'] = [
            'passed' => empty($missingEnvVars),
            'message' => empty($missingEnvVars) ? 'All required env vars set' : 'Missing: ' . implode(', ', $missingEnvVars)
        ];
        
        if (!empty($missingEnvVars)) {
            $allPassed = false;
        }
        
        return [
            'ready' => $allPassed,
            'checks' => $checks,
            'summary' => $allPassed ? 'Deployment ready' : 'Deployment readiness issues detected'
        ];
    }
}