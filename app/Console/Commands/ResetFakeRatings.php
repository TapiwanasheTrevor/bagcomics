<?php

namespace App\Console\Commands;

use App\Models\Comic;
use Illuminate\Console\Command;

class ResetFakeRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comics:reset-fake-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset fake seeded ratings and only use real user reviews';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting fake ratings...');

        // Reset all comics to have 0 rating and 0 total_ratings where they don't have actual reviews
        $comicsWithFakeRatings = Comic::where(function ($query) {
            $query->where('average_rating', '>', 0)
                  ->orWhere('total_ratings', '>', 0);
        })->whereDoesntHave('reviews')->get();

        $this->info("Found {$comicsWithFakeRatings->count()} comics with fake ratings");

        $progressBar = $this->output->createProgressBar($comicsWithFakeRatings->count());
        $progressBar->start();

        foreach ($comicsWithFakeRatings as $comic) {
            $comic->update([
                'average_rating' => 0,
                'total_ratings' => 0,
            ]);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Now recalculate ratings for comics that DO have reviews
        $comicsWithReviews = Comic::has('reviews')->get();
        $this->info("Recalculating ratings for {$comicsWithReviews->count()} comics with actual reviews");

        if ($comicsWithReviews->count() > 0) {
            $progressBar = $this->output->createProgressBar($comicsWithReviews->count());
            $progressBar->start();

            foreach ($comicsWithReviews as $comic) {
                $comic->updateAverageRating();
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();
        }

        $this->info('Fake ratings reset completed successfully!');
        
        return 0;
    }
}