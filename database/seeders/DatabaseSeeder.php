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
        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Run all seeders in order
        $this->call([
            AdminUserSeeder::class,     // Create admin users first
            ComicSeeder::class,
            PdfComicSeeder::class,
            CmsContentSeeder::class,    // Seed CMS content
            // UserLibrarySeeder::class,  // Don't auto-add comics to user library
            // UserProgressSeeder::class, // Don't auto-create reading progress
        ]);
    }
}
