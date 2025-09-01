<?php

namespace App\Console\Commands;

use App\Models\Comic;
use App\Models\ComicReview;
use Illuminate\Console\Command;

class FixReviewRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:fix-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix review ratings by approving unapproved reviews and recalculating comic ratings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting review ratings fix...');

        // Approve all unapproved reviews
        $unapprovedReviews = ComicReview::where('is_approved', false)->get();
        $this->info("Found {$unapprovedReviews->count()} unapproved reviews");

        foreach ($unapprovedReviews as $review) {
            $review->update(['is_approved' => true]);
        }

        $this->info('All reviews approved');

        // Recalculate all comic ratings
        $comics = Comic::has('reviews')->get();
        $this->info("Recalculating ratings for {$comics->count()} comics");

        $progressBar = $this->output->createProgressBar($comics->count());
        $progressBar->start();

        foreach ($comics as $comic) {
            $comic->updateAverageRating();
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info('Review ratings fix completed successfully!');
        
        return 0;
    }
}