<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserComicProgress;
use Illuminate\Database\Seeder;

class UserProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user (or create one if none exists)
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        // Get some comics to create progress for
        $comics = Comic::where('is_visible', true)->limit(15)->get();

        if ($comics->isEmpty()) {
            $this->command->info('No comics found. Please run ComicSeeder first.');
            return;
        }

        foreach ($comics as $index => $comic) {
            // Create different types of progress
            $progressType = $index % 4;
            
            switch ($progressType) {
                case 0: // Completed comics
                    UserComicProgress::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'comic_id' => $comic->id,
                        ],
                        [
                            'current_page' => $comic->page_count ?? 20,
                            'total_pages' => $comic->page_count ?? 20,
                            'progress_percentage' => 100.00,
                            'is_completed' => true,
                            'is_bookmarked' => rand(0, 1) === 1,
                            'reading_time_minutes' => rand(30, 120),
                            'last_read_at' => now()->subDays(rand(1, 30)),
                            'completed_at' => now()->subDays(rand(1, 30)),
                            'bookmarks' => rand(0, 1) === 1 ? [
                                [
                                    'page' => rand(5, 15),
                                    'note' => 'Great character development here!',
                                    'created_at' => now()->subDays(rand(1, 20))->toISOString(),
                                ],
                                [
                                    'page' => rand(16, 20),
                                    'note' => 'Amazing artwork in this scene',
                                    'created_at' => now()->subDays(rand(1, 15))->toISOString(),
                                ]
                            ] : null,
                        ]
                    );
                    break;

                case 1: // In progress comics
                    $currentPage = rand(2, ($comic->page_count ?? 20) - 1);
                    $totalPages = $comic->page_count ?? 20;
                    UserComicProgress::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'comic_id' => $comic->id,
                        ],
                        [
                            'current_page' => $currentPage,
                            'total_pages' => $totalPages,
                            'progress_percentage' => ($currentPage / $totalPages) * 100,
                            'is_completed' => false,
                            'is_bookmarked' => rand(0, 1) === 1,
                            'reading_time_minutes' => rand(15, 60),
                            'last_read_at' => now()->subDays(rand(1, 7)),
                            'completed_at' => null,
                            'bookmarks' => rand(0, 1) === 1 ? [
                                [
                                    'page' => rand(1, $currentPage),
                                    'note' => 'Need to remember this plot point',
                                    'created_at' => now()->subDays(rand(1, 5))->toISOString(),
                                ]
                            ] : null,
                        ]
                    );
                    break;

                case 2: // Recently started comics
                    UserComicProgress::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'comic_id' => $comic->id,
                        ],
                        [
                            'current_page' => rand(1, 3),
                            'total_pages' => $comic->page_count ?? 20,
                            'progress_percentage' => rand(5, 15),
                            'is_completed' => false,
                            'is_bookmarked' => false,
                            'reading_time_minutes' => rand(5, 20),
                            'last_read_at' => now()->subDays(rand(1, 3)),
                            'completed_at' => null,
                            'bookmarks' => null,
                        ]
                    );
                    break;

                case 3: // Comics with bookmarks but not much progress
                    UserComicProgress::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'comic_id' => $comic->id,
                        ],
                        [
                            'current_page' => rand(1, 5),
                            'total_pages' => $comic->page_count ?? 20,
                            'progress_percentage' => rand(5, 25),
                            'is_completed' => false,
                            'is_bookmarked' => true,
                            'reading_time_minutes' => rand(10, 30),
                            'last_read_at' => now()->subDays(rand(1, 14)),
                            'completed_at' => null,
                            'bookmarks' => [
                                [
                                    'page' => 1,
                                    'note' => 'Interesting premise, want to continue later',
                                    'created_at' => now()->subDays(rand(1, 10))->toISOString(),
                                ],
                                [
                                    'page' => rand(2, 5),
                                    'note' => 'Love this art style!',
                                    'created_at' => now()->subDays(rand(1, 8))->toISOString(),
                                ]
                            ],
                        ]
                    );
                    break;
            }
        }

        $this->command->info('User progress data seeded successfully!');
        $this->command->info("Created progress for {$comics->count()} comics for user: {$user->name}");
    }
}
