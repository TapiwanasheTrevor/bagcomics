<?php

namespace App\Observers;

use App\Models\Comic;
use App\Jobs\SendNewComicNotifications;
use Illuminate\Support\Facades\Log;

class ComicObserver
{
    /**
     * Handle the Comic "created" event.
     */
    public function created(Comic $comic): void
    {
        // Only send notifications for visible comics
        if ($comic->is_visible && $comic->published_at) {
            Log::info('New comic created, dispatching notifications', [
                'comic_id' => $comic->id,
                'comic_title' => $comic->title
            ]);

            // Dispatch notification job in the background
            SendNewComicNotifications::dispatch($comic);
        }
    }

    /**
     * Handle the Comic "updated" event.
     */
    public function updated(Comic $comic): void
    {
        // If comic was just made visible and published, send notifications
        if ($comic->wasChanged(['is_visible', 'published_at'])) {
            if ($comic->is_visible && $comic->published_at && !$comic->getOriginal('is_visible')) {
                Log::info('Comic published, dispatching notifications', [
                    'comic_id' => $comic->id,
                    'comic_title' => $comic->title
                ]);

                // Dispatch notification job in the background
                SendNewComicNotifications::dispatch($comic);
            }
        }
    }

    /**
     * Handle the Comic "deleted" event.
     */
    public function deleted(Comic $comic): void
    {
        Log::info('Comic deleted', [
            'comic_id' => $comic->id,
            'comic_title' => $comic->title
        ]);
    }

    /**
     * Handle the Comic "restored" event.
     */
    public function restored(Comic $comic): void
    {
        // If comic is restored and visible, send notifications
        if ($comic->is_visible && $comic->published_at) {
            Log::info('Comic restored, dispatching notifications', [
                'comic_id' => $comic->id,
                'comic_title' => $comic->title
            ]);

            SendNewComicNotifications::dispatch($comic);
        }
    }

    /**
     * Handle the Comic "force deleted" event.
     */
    public function forceDeleted(Comic $comic): void
    {
        Log::info('Comic permanently deleted', [
            'comic_id' => $comic->id,
            'comic_title' => $comic->title
        ]);
    }
}
