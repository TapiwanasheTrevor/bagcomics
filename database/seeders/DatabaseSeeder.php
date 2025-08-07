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
            User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        // Run essential seeders that don't require Faker
        $this->call([
            AdminUserSeeder::class,     // Create admin users first
            ComicSeeder::class,         // Create individual comics (has proper data)
            PdfComicSeeder::class,      // Add PDF comics (uses storage file)
            CmsContentSeeder::class,    // Seed CMS content
            // Temporarily disabled seeders that use Faker/factories:
            // ComicSeriesSeeder::class,   // Uses Comic::factory()
            // UserLibrarySeeder::class,   // May use factories
            // UserProgressSeeder::class,  // May use factories
            // ComicReviewSeeder::class,   // Uses factories
            // ComicBookmarkSeeder::class, // May use factories
            // SocialShareSeeder::class,   // Uses fake() calls
        ]);
    }
}
