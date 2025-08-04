<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user if it doesn't exist
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // Run all seeders in order
        $this->call([
            AdminUserSeeder::class,     // Create admin users first
            ComicSeriesSeeder::class,   // Create comic series first
            ComicSeeder::class,         // Then create individual comics
            PdfComicSeeder::class,      // Add PDF comics
            CmsContentSeeder::class,    // Seed CMS content
            UserLibrarySeeder::class,   // Add comics to user libraries
            UserProgressSeeder::class,  // Create reading progress
            ComicReviewSeeder::class,   // Create reviews and ratings
            ComicBookmarkSeeder::class, // Create bookmarks
            SocialShareSeeder::class,   // Create social shares
        ]);
    }
}
