<?php

namespace App\Jobs;

use App\Models\Comic;
use App\Services\ComicNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNewComicNotifications implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    public function __construct(
        public Comic $comic
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ComicNotificationService $notificationService): void
    {
        try {
            $recipientsCount = $notificationService->sendNewComicNotifications($this->comic);
            
            Log::info('New comic notifications job completed successfully', [
                'comic_id' => $this->comic->id,
                'comic_title' => $this->comic->title,
                'recipients_count' => $recipientsCount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send new comic notifications', [
                'comic_id' => $this->comic->id,
                'comic_title' => $this->comic->title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-throw to trigger job retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('New comic notifications job failed permanently', [
            'comic_id' => $this->comic->id,
            'comic_title' => $this->comic->title,
            'error' => $exception->getMessage()
        ]);
    }
}
