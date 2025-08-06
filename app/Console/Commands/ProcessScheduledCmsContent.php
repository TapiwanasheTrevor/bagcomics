<?php

namespace App\Console\Commands;

use App\Services\CmsService;
use Illuminate\Console\Command;

class ProcessScheduledCmsContent extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:process-scheduled';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled CMS content for publishing';

    /**
     * Execute the console command.
     */
    public function handle(CmsService $cmsService): int
    {
        $this->info('Processing scheduled CMS content...');
        
        $published = $cmsService->processScheduledContent();
        
        if ($published > 0) {
            $this->info("Successfully published {$published} scheduled content items.");
        } else {
            $this->info('No scheduled content ready for publishing.');
        }
        
        return Command::SUCCESS;
    }
}