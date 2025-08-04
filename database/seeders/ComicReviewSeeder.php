<?php

namespace Database\Seeders;

use App\Models\Comic;
use App\Models\ComicReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class ComicReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $comics = Comic::all();

        if ($users->isEmpty() || $comics->isEmpty()) {
            $this->command->warn('No users or comics found. Please run UserSeeder and ComicSeeder first.');
            return;
        }

        // Create reviews for random comic-user combinations
        $reviewCount = min(100, $users->count() * 3); // Max 3 reviews per user on average

        for ($i = 0; $i < $reviewCount; $i++) {
            $user = $users->random();
            $comic = $comics->random();

            // Check if user already reviewed this comic
            if (ComicReview::where('user_id', $user->id)->where('comic_id', $comic->id)->exists()) {
                continue;
            }

            $review = ComicReview::factory()->create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
            ]);

            // Add some random votes to reviews
            if (rand(1, 100) <= 60) { // 60% chance of having votes
                $voterCount = rand(1, min(10, $users->count()));
                $voters = $users->random($voterCount);
                
                foreach ($voters as $voter) {
                    if ($voter->id !== $user->id) { // Users can't vote on their own reviews
                        $review->addHelpfulVote($voter, fake()->boolean(70)); // 70% helpful votes
                    }
                }
            }
        }

        // Update comic ratings based on reviews
        foreach ($comics as $comic) {
            $comic->updateAverageRating();
        }

        $this->command->info('Created reviews and updated comic ratings.');
    }
}