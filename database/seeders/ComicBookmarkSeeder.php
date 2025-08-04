<?php

namespace Database\Seeders;

use App\Models\Comic;
use App\Models\ComicBookmark;
use App\Models\User;
use App\Models\UserLibrary;
use Illuminate\Database\Seeder;

class ComicBookmarkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Create bookmarks for users who have comics in their library
        foreach ($users as $user) {
            $userComics = UserLibrary::where('user_id', $user->id)->with('comic')->get();
            
            if ($userComics->isEmpty()) {
                continue;
            }

            // Create 1-3 bookmarks per user for random comics they own
            $bookmarkCount = rand(1, min(3, $userComics->count()));
            $selectedComics = $userComics->random($bookmarkCount);

            foreach ($selectedComics as $libraryEntry) {
                $comic = $libraryEntry->comic;
                $maxPage = $comic->page_count ?? 50;
                
                // Create 1-2 bookmarks per comic
                $bookmarksPerComic = rand(1, 2);
                
                for ($i = 0; $i < $bookmarksPerComic; $i++) {
                    ComicBookmark::factory()->create([
                        'user_id' => $user->id,
                        'comic_id' => $comic->id,
                        'page_number' => rand(1, $maxPage),
                    ]);
                }
            }
        }

        $this->command->info('Created bookmarks for users.');
    }
}