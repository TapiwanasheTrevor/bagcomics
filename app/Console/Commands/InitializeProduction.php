<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DeploymentService;
use App\Services\SecurityService;
use App\Services\CacheService;
use App\Services\DatabaseOptimizationService;

class InitializeProduction extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bagcomics:init-production 
                            {--force : Force initialization even if already initialized}
                            {--skip-backup : Skip pre-deployment backup}
                            {--skip-optimization : Skip database optimization}';

    /**
     * The console command description.
     */
    protected $description = 'Initialize BAG Comics for production deployment';

    private DeploymentService $deploymentService;
    private SecurityService $securityService;
    private CacheService $cacheService;
    private DatabaseOptimizationService $dbOptimizationService;

    public function __construct(
        DeploymentService $deploymentService,
        SecurityService $securityService,
        CacheService $cacheService,
        DatabaseOptimizationService $dbOptimizationService
    ) {
        parent::__construct();
        $this->deploymentService = $deploymentService;
        $this->securityService = $securityService;
        $this->cacheService = $cacheService;
        $this->dbOptimizationService = $dbOptimizationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Initializing BAG Comics for Production Deployment');
        $this->newLine();

        // Check if already initialized (unless force flag is used)
        if (!$this->option('force') && $this->isAlreadyInitialized()) {
            $this->warn('Application appears to already be initialized for production.');
            if (!$this->confirm('Do you want to continue anyway?')) {
                return Command::SUCCESS;
            }
        }

        // Create pre-deployment backup
        if (!$this->option('skip-backup')) {
            $this->createBackup();
        }

        // Verify deployment readiness
        $this->verifyReadiness();

        // Initialize production configuration
        $this->initializeProduction();

        // Optimize database
        if (!$this->option('skip-optimization')) {
            $this->optimizeDatabase();
        }

        // Warm up caches
        $this->warmupCaches();

        // Final health check
        $this->performHealthCheck();

        $this->newLine();
        $this->info('✅ Production initialization completed successfully!');
        $this->newLine();

        return Command::SUCCESS;
    }

    private function isAlreadyInitialized(): bool
    {
        // Check if initialization marker exists
        return file_exists(storage_path('app/.production_initialized'));
    }

    private function createBackup(): void
    {
        $this->info('📦 Creating pre-deployment backup...');
        
        with($this->output->createProgressBar(3), function ($bar) {
            $bar->start();
            
            $backup = $this->deploymentService->createPreDeploymentBackup();
            $bar->advance();
            
            if (isset($backup['error'])) {
                $this->error('Backup creation failed: ' . $backup['error']);
                $bar->finish();
                return;
            }
            
            $bar->advance();
            
            $this->info("\n✅ Backup created successfully");
            if (isset($backup['files_manifest'])) {
                $this->line('   📄 Files manifest: Created');
            }
            if (isset($backup['configuration'])) {
                $this->line('   ⚙️ Configuration backup: Created');
            }
            
            $bar->finish();
        });
        
        $this->newLine();
    }

    private function verifyReadiness(): void
    {
        $this->info('🔍 Verifying deployment readiness...');
        
        $readiness = $this->deploymentService->verifyDeploymentReadiness();
        
        foreach ($readiness['checks'] as $check => $result) {
            $icon = $result['passed'] ? '✅' : '❌';
            $this->line("   {$icon} " . ucwords(str_replace('_', ' ', $check)) . ": {$result['message']}");
        }
        
        if (!$readiness['ready']) {
            $this->error('❌ Deployment readiness check failed. Please resolve the issues above.');
            exit(1);
        }
        
        $this->info('✅ All readiness checks passed');
        $this->newLine();
    }

    private function initializeProduction(): void
    {
        $this->info('⚙️ Initializing production configuration...');
        
        $result = $this->deploymentService->initializeProduction();
        
        if (!$result['success']) {
            $this->error('❌ Production initialization failed: ' . $result['error']);
            exit(1);
        }
        
        // Display results
        foreach ($result['results'] as $category => $details) {
            $this->line("   📁 " . ucwords($category) . ":");
            if (is_array($details)) {
                foreach ($details as $key => $value) {
                    $this->line("      • " . ucwords(str_replace('_', ' ', $key)) . ": {$value}");
                }
            } else {
                $this->line("      • {$details}");
            }
        }
        
        // Create initialization marker
        file_put_contents(
            storage_path('app/.production_initialized'), 
            json_encode([
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0')
            ])
        );
        
        $this->info('✅ Production configuration initialized');
        $this->newLine();
    }

    private function optimizeDatabase(): void
    {
        $this->info('🗄️ Optimizing database performance...');
        
        with($this->output->createProgressBar(4), function ($bar) {
            $bar->start();
            
            // Add indexes
            $this->dbOptimizationService->addOptimizedIndexes();
            $bar->advance();
            
            // Optimize queries
            $optimizations = $this->dbOptimizationService->optimizeCommonQueries();
            $bar->advance();
            
            // Update statistics
            $this->dbOptimizationService->updateStatistics();
            $bar->advance();
            
            // Optimize tables
            $this->dbOptimizationService->optimizeTables();
            $bar->advance();
            
            $bar->finish();
        });
        
        $this->info("\n✅ Database optimization completed");
        $this->newLine();
    }

    private function warmupCaches(): void
    {
        $this->info('🔥 Warming up application caches...');
        
        with($this->output->createProgressBar(3), function ($bar) {
            $bar->start();
            
            // Clear existing caches
            $this->call('config:clear');
            $this->call('cache:clear');
            $bar->advance();
            
            // Cache configurations
            $this->call('config:cache');
            if (!config('app.debug')) {
                $this->call('route:cache');
                $this->call('view:cache');
            }
            $bar->advance();
            
            // Warm up application-specific caches
            $this->cacheService->warmupCaches();
            $bar->advance();
            
            $bar->finish();
        });
        
        $this->info("\n✅ Caches warmed up successfully");
        $this->newLine();
    }

    private function performHealthCheck(): void
    {
        $this->info('🏥 Performing final health check...');
        
        $health = $this->deploymentService->getSystemHealth();
        
        $allHealthy = true;
        foreach ($health as $component => $status) {
            $icon = $status['status'] === 'healthy' ? '✅' : '❌';
            $this->line("   {$icon} " . ucwords($component) . ": {$status['message']}");
            
            if ($status['status'] !== 'healthy') {
                $allHealthy = false;
            }
        }
        
        if (!$allHealthy) {
            $this->warn('⚠️ Some health checks failed. The application may still function, but please review the issues.');
        } else {
            $this->info('✅ All health checks passed');
        }
        
        $this->newLine();
    }
}